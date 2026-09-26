<?php

namespace Modules\Attendance\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Academic\Models\Semester;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Attendance\Resources\TeachingSessionResource;
use Modules\Attendance\Support\AttendanceAccess;
use Modules\Attendance\Support\AttendancePolicy;
use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Student\Models\Student;
use Modules\Student\Resources\StudentResource;

class AttendanceService
{
    /**
     * Buat sesi perkuliahan.
     *
     * Sengaja TIDAK membuat baris presensi apa pun: kehadiran adalah fakta yang
     * harus dicatat, bukan default. Baris `present` otomatis membuat kelas tampak
     * hadir 100% walau tak pernah diabsen.
     */
    public function createSession(array $data): TeachingSession
    {
        $classId = (int) $data['academic_class_id'];
        $meeting = (int) $data['meeting_number'];

        if (TeachingSession::where('academic_class_id', $classId)->where('meeting_number', $meeting)->exists()) {
            throw ValidationException::withMessages([
                'meeting_number' => "Pertemuan ke-{$meeting} sudah terdaftar pada kelas ini.",
            ]);
        }

        $data['status'] = $data['status'] ?? SessionStatus::SCHEDULED->value;
        $data['check_in_code'] = null;
        $data['check_in_expires_at'] = null;

        $session = TeachingSession::create($data);

        AuditService::log(
            action: 'created',
            module: 'Attendance',
            description: "Teaching session #{$session->id} (pertemuan {$meeting}) dibuat untuk kelas {$session->academicClass?->code}.",
            entity: $session,
            oldValues: null,
            newValues: $session->only(['academic_class_id', 'meeting_number', 'session_date', 'lecturer_id', 'status'])
        );

        return $session->load(['academicClass', 'lecturer', 'room']);
    }

    /**
     * Perubahan BAP/keterangan sesi. Sesi yang sudah ditutup hanya boleh diubah
     * lewat jalur koreksi beralasan supaya perubahannya bisa ditelusuri.
     */
    public function updateSession(TeachingSession $session, array $data, ?string $correctionReason = null): TeachingSession
    {
        $this->assertSessionEditable($session, $correctionReason);

        if (isset($data['status'])) {
            $target = SessionStatus::tryFrom((string) $data['status']);

            if (!$target) {
                throw ValidationException::withMessages(['status' => 'Status sesi tidak dikenali.']);
            }

            if ($target !== $session->status && !$session->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Transisi status tidak diizinkan: {$session->status->value} → {$target->value}.",
                ]);
            }
        }

        unset($data['check_in_code'], $data['check_in_expires_at']);

        $oldValues = $session->only(array_keys($data));
        $session->update($data);

        AuditService::log(
            action: $correctionReason ? 'session_corrected' : 'session_updated',
            module: 'Attendance',
            description: $correctionReason
                ? "Sesi #{$session->id} dikoreksi: {$correctionReason}"
                : "Sesi #{$session->id} diperbarui.",
            entity: $session,
            oldValues: $oldValues,
            newValues: $session->only(array_keys($data))
        );

        return $session->fresh(['academicClass', 'lecturer', 'room', 'attendances.student']);
    }

    public function deleteSession(TeachingSession $session): void
    {
        if ($session->attendances()->whereNotNull('recorded_at')->exists()) {
            throw ValidationException::withMessages([
                'session' => 'Sesi ini sudah punya presensi tercatat dan tidak boleh dihapus. Batalkan sesi atau sinkronkan ulang jadwal.',
            ]);
        }

        $snapshot = $session->only(['academic_class_id', 'meeting_number', 'session_date', 'status']);

        AuditService::log(
            action: 'deleted',
            module: 'Attendance',
            description: "Teaching session #{$session->id} dihapus.",
            entity: $session,
            oldValues: $snapshot,
            newValues: null
        );

        $session->delete();
    }

    /**
     * Catat presensi massal untuk satu sesi. All-or-nothing: sebagian baris gagal
     * berarti tidak ada yang tersimpan, supaya daftar hadir tidak separuh jadi.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function recordBatch(
        TeachingSession $session,
        array $rows,
        ?int $userId = null,
        ?string $correctionReason = null
    ): void {
        $rows = array_values($rows);

        if (!$rows) {
            throw ValidationException::withMessages(['attendances' => 'Tidak ada data presensi untuk disimpan.']);
        }

        $this->assertSessionEditable($session, $correctionReason);

        $studentIds = array_values(array_unique(array_map(
            fn (array $row) => (int) $row['student_id'],
            $rows
        )));

        $this->assertOnRoster($session, $studentIds);

        $changes = [];

        DB::transaction(function () use ($session, $rows, $userId, &$changes) {
            foreach ($rows as $row) {
                $changes[] = $this->writeAttendance(
                    session: $session,
                    studentId: (int) $row['student_id'],
                    status: $this->statusOf($row['status']),
                    notes: $row['notes'] ?? null,
                    userId: $userId
                );
            }
        });

        if (!$changes) {
            return;
        }

        AuditService::log(
            action: $correctionReason ? 'attendance_corrected' : 'attendance_recorded',
            module: 'Attendance',
            description: $correctionReason
                ? "Koreksi presensi sesi #{$session->id}: {$correctionReason}"
                : "Presensi sesi #{$session->id} dicatat (" . count($changes) . ' mahasiswa).',
            entity: $session,
            oldValues: ['changes' => array_map(fn (array $c) => ['student_id' => $c['student_id'], 'status' => $c['before']], $changes)],
            newValues: ['changes' => array_map(fn (array $c) => ['student_id' => $c['student_id'], 'status' => $c['after']], $changes)]
        );
    }

    /**
     * Koreksi satu baris presensi.
     */
    public function recordSingle(
        TeachingSession $session,
        Student $student,
        AttendanceStatus $status,
        ?string $notes,
        ?int $userId = null,
        ?string $correctionReason = null
    ): StudentAttendance {
        $this->assertSessionEditable($session, $correctionReason);
        $this->assertOnRoster($session, [$student->id]);

        $change = $this->writeAttendance($session, $student->id, $status, $notes, $userId);

        AuditService::log(
            action: $correctionReason ? 'attendance_corrected' : 'attendance_recorded',
            module: 'Attendance',
            description: $correctionReason
                ? "Koreksi presensi {$student->full_name} pada sesi #{$session->id}: {$correctionReason}"
                : "Presensi {$student->full_name} pada sesi #{$session->id}: {$change['before']} → {$change['after']}",
            entity: $session,
            oldValues: ['student_id' => $student->id, 'status' => $change['before']],
            newValues: ['student_id' => $student->id, 'status' => $change['after'], 'notes' => $notes]
        );

        return $change['attendance']->load('student.studyProgram');
    }

    /**
     * Satu tulisan presensi + delta perubahan untuk bahan audit.
     *
     * @return array{attendance: StudentAttendance, student_id: int, before: string|null, after: string}
     */
    protected function writeAttendance(
        TeachingSession $session,
        int $studentId,
        AttendanceStatus $status,
        ?string $notes,
        ?int $userId
    ): array {
        if ($status->requiresNote() && blank($notes)) {
            throw ValidationException::withMessages([
                'attendances' => "Status {$status->label()} wajib disertai keterangan ({$studentId}).",
            ]);
        }

        $existing = StudentAttendance::where('teaching_session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();

        $before = $existing?->status?->value;

        $attendance = StudentAttendance::updateOrCreate(
            [
                'teaching_session_id' => $session->id,
                'student_id' => $studentId,
            ],
            [
                'academic_class_id' => $session->academic_class_id,
                'status' => $status,
                'notes' => $notes,
                'recorded_by' => $userId,
                'recorded_at' => now(),
            ]
        );

        return [
            'attendance' => $attendance,
            'student_id' => $studentId,
            'before' => $before,
            'after' => $status->value,
        ];
    }

    protected function statusOf(mixed $status): AttendanceStatus
    {
        if ($status instanceof AttendanceStatus) {
            return $status;
        }

        $enum = AttendanceStatus::tryFrom((string) $status);

        if (!$enum) {
            throw ValidationException::withMessages(['status' => "Status presensi tidak dikenali: {$status}"]);
        }

        return $enum;
    }

    /**
     * Sesi terkunci (closed) hanya boleh ditulis ulang lewat alasan koreksi.
     */
    protected function assertSessionEditable(TeachingSession $session, ?string $correctionReason): void
    {
        if ($session->status === SessionStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'session' => 'Sesi dibatalkan dan tidak dapat diabsen. Jadwalkan ulang pertemuan ini.',
            ]);
        }

        if ($session->status->isLocked() && blank($correctionReason)) {
            throw ValidationException::withMessages([
                'correction_reason' => 'Sesi sudah ditutup (terkunci). Isi alasan koreksi untuk mengubah presensinya.',
            ]);
        }
    }

    /**
     * Presensi hanya boleh tercatat untuk peserta aktif kelas tersebut.
     *
     * @param  array<int, int>  $studentIds
     */
    protected function assertOnRoster(TeachingSession $session, array $studentIds): void
    {
        $roster = AttendanceAccess::rosterStudentIds((int) $session->academic_class_id);
        $foreign = array_values(array_diff($studentIds, $roster));

        if (!$foreign) {
            return;
        }

        $names = Student::whereIn('id', $foreign)->pluck('student_number', 'id');
        $list = collect($studentIds)->intersect($foreign)
            ->map(fn ($id) => $names[$id] ?? "#{$id}")
            ->implode(', ');

        throw ValidationException::withMessages([
            'attendances' => "Mahasiswa berikut bukan peserta kelas ini: {$list}.",
        ]);
    }

    /**
     * Daftar absen yang dilihat dosen: roster kelas digabung dengan baris yang
     * sudah tersimpan. Mahasiswa yang belum diabsen kembali dengan status null —
     * bukan `present` — supaya "belum dicatat" tidak pernah terbaca sebagai hadir.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSessionAttendanceSheet(TeachingSession $session): array
    {
        $rosterIds = AttendanceAccess::rosterStudentIds((int) $session->academic_class_id);

        $attendances = StudentAttendance::where('teaching_session_id', $session->id)
            ->with(['student.studyProgram'])
            ->get();

        $recordedByStudent = $attendances->keyBy('student_id');

        $extraIds = $attendances->pluck('student_id')->diff($rosterIds)->values()->all();

        $students = Student::with('studyProgram')
            ->whereIn('id', array_merge($rosterIds, $extraIds))
            ->get()
            ->keyBy('id');

        $sheet = [];

        foreach ($this->sortStudentsByIds($students, $rosterIds) as $student) {
            $sheet[] = $this->attendanceSheetRow($session, $student, $recordedByStudent->get($student->id), true);
        }

        foreach ($this->sortStudentsByIds($students, $extraIds) as $student) {
            $sheet[] = $this->attendanceSheetRow($session, $student, $recordedByStudent->get($student->id), false);
        }

        return $sheet;
    }

    /**
     * @return array<string, mixed>
     */
    protected function attendanceSheetRow(
        TeachingSession $session,
        Student $student,
        ?StudentAttendance $attendance,
        bool $isEnrolled
    ): array {
        $status = $attendance?->status;

        return [
            'id' => $attendance?->id,
            'teaching_session_id' => $session->id,
            'student_id' => $student->id,
            'academic_class_id' => $session->academic_class_id,
            'status' => $status?->value,
            'status_label' => $status?->label() ?? 'Belum dicatat',
            'status_code' => $status?->code() ?? '-',
            'notes' => $attendance?->notes,
            'attachment_path' => $attendance?->attachment_path,
            'recorded_by' => $attendance?->recorded_by,
            'recorded_at' => $attendance?->recorded_at?->toISOString(),
            'is_recorded' => $attendance !== null,
            'is_enrolled' => $isEnrolled,
            'student' => new StudentResource($student),
            'created_at' => $attendance?->created_at?->toISOString(),
            'updated_at' => $attendance?->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Student>  $students  Keyed by student id.
     * @param  array<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, Student>
     */
    protected function sortStudentsByIds($students, array $ids)
    {
        return collect($ids)
            ->map(fn ($id) => $students->get($id))
            ->filter()
            ->sortBy(fn (Student $student) => $student->student_number)
            ->values();
    }

    /**
     * Buka token presensi mandiri. Hanya sesi yang sedang berjalan; sesi tertutup
     * harus dibuka ulang lewat reopenSession() yang mewajibkan alasan.
     */
    public function openCheckIn(TeachingSession $session, int $durationMinutes = 15): TeachingSession
    {
        if (in_array($session->status, [SessionStatus::CLOSED, SessionStatus::CANCELLED], true)) {
            throw ValidationException::withMessages([
                'session' => "Sesi berstatus {$session->status->value}. Buka ulang sesi dengan alasan sebelum membuka presensi.",
            ]);
        }

        $durationMinutes = max(1, min(120, $durationMinutes));

        $session->update([
            'check_in_code' => $this->freshCheckInCode(),
            'check_in_expires_at' => now()->addMinutes($durationMinutes),
            'status' => SessionStatus::OPEN,
        ]);

        AuditService::log(
            action: 'checkin_opened',
            module: 'Attendance',
            description: "Presensi mandiri sesi #{$session->id} dibuka {$durationMinutes} menit.",
            entity: $session,
            oldValues: null,
            newValues: ['expires_at' => $session->check_in_expires_at->toIso8601String()]
        );

        return $session;
    }

    /**
     * Kode tidak boleh tabrakan dengan sesi yang tokennya masih hidup, karena
     * pencocokan kode adalah satu-satunya bukti "mahasiswa ini ada di kelas itu".
     */
    protected function freshCheckInCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (TeachingSession::where('check_in_code', $code)
            ->whereNotNull('check_in_expires_at')
            ->where('check_in_expires_at', '>', now())
            ->exists());

        return $code;
    }

    public function closeSession(TeachingSession $session): TeachingSession
    {
        if (!$session->status->canTransitionTo(SessionStatus::CLOSED)) {
            throw ValidationException::withMessages([
                'session' => "Sesi berstatus {$session->status->value} tidak dapat ditutup.",
            ]);
        }

        $session->update([
            'status' => SessionStatus::CLOSED,
            'check_in_code' => null,
            'check_in_expires_at' => null,
        ]);

        AuditService::log(
            action: 'session_closed',
            module: 'Attendance',
            description: "Sesi #{$session->id} ditutup; presensinya terkunci.",
            entity: $session,
            oldValues: null,
            newValues: ['status' => SessionStatus::CLOSED->value]
        );

        return $session;
    }

    /**
     * Membuka kembali sesi yang sudah terkunci. Wajib alasan dan selalu ter-audit.
     */
    public function reopenSession(TeachingSession $session, string $reason): TeachingSession
    {
        if (!$session->status->canTransitionTo(SessionStatus::OPEN)) {
            throw ValidationException::withMessages([
                'session' => "Sesi berstatus {$session->status->value} tidak perlu/tidak dapat dibuka ulang.",
            ]);
        }

        $session->update(['status' => SessionStatus::OPEN]);

        AuditService::log(
            action: 'session_reopened',
            module: 'Attendance',
            description: "Sesi #{$session->id} dibuka kembali: {$reason}",
            entity: $session,
            oldValues: ['status' => SessionStatus::CLOSED->value],
            newValues: ['status' => SessionStatus::OPEN->value, 'reason' => $reason]
        );

        return $session;
    }

    /**
     * Presensi mandiri oleh mahasiswa dengan token.
     */
    public function selfCheckIn(TeachingSession $session, string $code, Student $student, ?int $userId = null): StudentAttendance
    {
        $expected = (string) $session->check_in_code;

        if ($expected === '' || !hash_equals(strtoupper($expected), strtoupper(trim($code)))) {
            throw ValidationException::withMessages(['check_in_code' => 'Kode presensi tidak valid.']);
        }

        if (!$session->check_in_expires_at || $session->check_in_expires_at->isPast()) {
            throw ValidationException::withMessages(['check_in_code' => 'Sesi presensi mandiri telah berakhir atau kedaluwarsa.']);
        }

        if (!AttendanceAccess::isOnRoster((int) $session->academic_class_id, (int) $student->id)) {
            throw ValidationException::withMessages([
                'teaching_session_id' => 'Anda tidak terdaftar sebagai peserta pada kelas pertemuan ini.',
            ]);
        }

        $existing = StudentAttendance::where('teaching_session_id', $session->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing && $existing->status === AttendanceStatus::PRESENT && $existing->recorded_by !== null) {
            throw ValidationException::withMessages(['check_in_code' => 'Anda sudah tercatat hadir pada pertemuan ini.']);
        }

        $attendance = StudentAttendance::updateOrCreate(
            [
                'teaching_session_id' => $session->id,
                'student_id' => $student->id,
            ],
            [
                'academic_class_id' => $session->academic_class_id,
                'status' => AttendanceStatus::PRESENT,
                'recorded_by' => $userId,
                'recorded_at' => now(),
                'notes' => 'Presensi mandiri (token)',
            ]
        );

        AuditService::log(
            action: 'self_checkin',
            module: 'Attendance',
            description: "{$student->student_number} presensi mandiri pada sesi #{$session->id}.",
            entity: $attendance,
            oldValues: null,
            newValues: ['status' => AttendanceStatus::PRESENT->value]
        );

        return $attendance->load(['teachingSession', 'student']);
    }

    /**
     * Rekapitulasi kehadiran kelas (matriks pertemuan).
     *
     * Persentase dihitung terhadap pertemuan yang SUDAH berlangsung, bukan seluruh
     * sesi yang ter-generate — denominator penuh membuat kelas di awal semester
     * selalu tampak tidak layak ujian.
     */
    public function getClassRecap(AcademicClass $class): array
    {
        $sessions = TeachingSession::where('academic_class_id', $class->id)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $heldIds = AttendancePolicy::heldSessions($sessions)->pluck('id')->all();
        $threshold = AttendancePolicy::threshold($class->semester);
        $rosterIds = AttendanceAccess::rosterStudentIds((int) $class->id);

        $attendances = StudentAttendance::where('academic_class_id', $class->id)->get();

        $matrix = [];

        foreach ($this->studentsByIds($rosterIds) as $student) {
            $rows = $attendances->where('student_id', $student->id);
            $counts = $this->countStatuses($rows, $heldIds);
            $attended = $counts['present'] + $counts['permit'] + $counts['sick'];

            $eligibility = AttendancePolicy::eligibility($attended, count($heldIds), $class->semester);

            $meetings = [];
            foreach ($sessions as $session) {
                $att = $rows->firstWhere('teaching_session_id', $session->id);
                $meetings[$session->meeting_number] = [
                    'session_id' => $session->id,
                    'meeting_number' => $session->meeting_number,
                    'session_date' => $session->session_date?->format('Y-m-d'),
                    'status' => $this->meetingStatus($att, in_array($session->id, $heldIds, true)),
                    'status_code' => $att ? $att->status->code() : '-',
                    'notes' => $att?->notes,
                ];
            }

            $matrix[] = [
                'student' => [
                    'id' => $student->id,
                    'student_number' => $student->student_number,
                    'full_name' => $student->full_name,
                    'photo_path' => $student->photo_path,
                    'study_program' => $student->studyProgram?->name,
                ],
                'present_count' => $counts['present'],
                'permit_count' => $counts['permit'],
                'sick_count' => $counts['sick'],
                'absent_count' => $counts['absent'],
                'unrecorded_count' => $counts['unrecorded'],
                'total_meetings' => count($heldIds),
                'percentage' => $eligibility['percentage'],
                'min_attendance_percentage' => $threshold['percentage'],
                'is_eligible' => $eligibility['is_eligible'],
                'meetings' => $meetings,
            ];
        }

        return [
            'class' => [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'section' => $class->section,
                'course' => $class->course,
                'semester' => $class->semester,
            ],
            'total_sessions' => $sessions->count(),
            'held_sessions' => count($heldIds),
            'threshold_stage' => $threshold['stage'],
            'min_attendance_percentage' => $threshold['percentage'],
            'sessions' => TeachingSessionResource::collection($sessions)->resolve(),
            'recap' => $matrix,
        ];
    }

    /**
     * Rekap kehadiran satu mahasiswa lintas kelas pada satu semester.
     */
    public function getStudentRecap(Student $student, ?int $semesterId = null): array
    {
        $enrollmentQuery = $student->enrollments()
            ->whereIn('status', [
                \Modules\Enrollment\Enums\EnrollmentStatus::APPROVED->value,
                \Modules\Enrollment\Enums\EnrollmentStatus::LOCKED->value,
            ])
            ->with([
                'items.academicClass.course',
                'items.academicClass.lecturers',
                'items.academicClass.schedules.room',
                'items.academicClass.semester',
                'semester',
            ]);

        if ($semesterId) {
            $enrollmentQuery->where('semester_id', $semesterId);
        }

        $classBreakdowns = [];
        $totals = [
            'sessions' => 0, 'present' => 0, 'permit' => 0, 'sick' => 0,
            'absent' => 0, 'unrecorded' => 0, 'classes' => 0,
        ];
        $threshold = AttendancePolicy::threshold($this->thresholdSemester($semesterId));

        foreach ($enrollmentQuery->get() as $enrollment) {
            foreach ($enrollment->items as $item) {
                if (!$item->academicClass || $item->status?->value === 'dropped' || $item->status?->value === 'cancelled') {
                    continue;
                }

                $class = $item->academicClass;
                $totals['classes']++;

                $sessions = TeachingSession::where('academic_class_id', $class->id)
                    ->with(['lecturer', 'room'])
                    ->orderBy('session_date')
                    ->orderBy('start_time')
                    ->get();

                $heldIds = AttendancePolicy::heldSessions($sessions)->pluck('id')->all();
                $classThreshold = AttendancePolicy::threshold($class->semester ?: $enrollment->semester);

                $rows = StudentAttendance::where('academic_class_id', $class->id)
                    ->where('student_id', $student->id)
                    ->get();

                $counts = $this->countStatuses($rows, $heldIds);
                $attended = $counts['present'] + $counts['permit'] + $counts['sick'];
                $eligibility = AttendancePolicy::eligibility($attended, count($heldIds), $class->semester ?: $enrollment->semester);

                $meetingsDetail = [];
                foreach ($sessions as $session) {
                    $att = $rows->firstWhere('teaching_session_id', $session->id);
                    $isHeld = in_array($session->id, $heldIds, true);

                    $meetingsDetail[] = [
                        'session_id' => $session->id,
                        'meeting_number' => $session->meeting_number,
                        'session_date' => $session->session_date?->format('Y-m-d'),
                        'start_time' => $session->start_time ? substr((string) $session->start_time, 0, 5) : null,
                        'end_time' => $session->end_time ? substr((string) $session->end_time, 0, 5) : null,
                        'topic' => $session->topic,
                        'teaching_method' => $session->teaching_method?->value ?? $session->teaching_method,
                        'teaching_method_label' => $session->teaching_method?->label(),
                        'lecturer_name' => $session->lecturer?->full_name,
                        'room' => $session->room?->code,
                        'status' => $this->meetingStatus($att, $isHeld),
                        'status_label' => $att
                            ? $att->status->label()
                            : ($isHeld ? 'Belum dicatat (dihitung alpa)' : 'Belum berlangsung'),
                        'status_code' => $att ? $att->status->code() : ($isHeld ? 'A' : '-'),
                        'is_recorded' => $att !== null,
                        'notes' => $att?->notes,
                        'is_check_in_active' => $session->check_in_code
                            && $session->check_in_expires_at
                            && $session->check_in_expires_at->isFuture(),
                    ];
                }

                $classBreakdowns[] = [
                    'class_id' => $class->id,
                    'class_code' => $class->code,
                    'class_name' => $class->name,
                    'section' => $class->section,
                    'course_code' => $class->course?->code,
                    'course_name' => $class->course?->name,
                    'credits' => $class->course?->credits,
                    'semester_name' => ($class->semester ?: $enrollment->semester)?->name,
                    'lecturers' => $class->lecturers,
                    'total_sessions' => $sessions->count(),
                    'held_sessions' => count($heldIds),
                    'present_count' => $counts['present'],
                    'permit_count' => $counts['permit'],
                    'sick_count' => $counts['sick'],
                    'absent_count' => $counts['absent'],
                    'unrecorded_count' => $counts['unrecorded'],
                    'percentage' => $eligibility['percentage'],
                    'min_attendance_percentage' => $classThreshold['percentage'],
                    'threshold_stage' => $classThreshold['stage'],
                    'is_eligible' => $eligibility['is_eligible'],
                    'meetings' => $meetingsDetail,
                ];

                $totals['sessions'] += count($heldIds);
                $totals['present'] += $counts['present'];
                $totals['permit'] += $counts['permit'];
                $totals['sick'] += $counts['sick'];
                $totals['absent'] += $counts['absent'];
                $totals['unrecorded'] += $counts['unrecorded'];
            }
        }

        $overall = AttendancePolicy::eligibility(
            $totals['present'] + $totals['permit'] + $totals['sick'],
            $totals['sessions'],
            $this->thresholdSemester($semesterId)
        );

        return [
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'study_program' => $student->studyProgram?->name,
            ],
            'summary' => [
                'total_classes' => $totals['classes'],
                'total_sessions' => $totals['sessions'],
                'total_present' => $totals['present'],
                'total_permit' => $totals['permit'],
                'total_sick' => $totals['sick'],
                'total_absent' => $totals['absent'],
                'total_unrecorded' => $totals['unrecorded'],
                'overall_percentage' => $overall['percentage'],
                'min_attendance_percentage' => $threshold['percentage'],
                'threshold_stage' => $threshold['stage'],
                'is_eligible_overall' => $overall['is_eligible'],
            ],
            'classes' => $classBreakdowns,
        ];
    }

    /**
     * Status satu pertemuan pada matriks: baris tercatat memakai statusnya; pertemuan
     * yang sudah lewat tapi belum diabsen dihitung alpa; pertemuan masa depan null.
     */
    protected function meetingStatus(?StudentAttendance $attendance, bool $isHeld): ?string
    {
        if ($attendance) {
            return $attendance->status->value;
        }

        return $isHeld ? AttendanceStatus::ABSENT->value : null;
    }

    /**
     * Hitung status hanya dari sesi yang sudah berlangsung supaya tidak menghitung
     * baris presensi masa depan/sesi batal.
     *
     * @param  \Illuminate\Support\Collection<int, StudentAttendance>  $rows
     * @param  array<int, int>  $heldIds
     * @return array{present: int, permit: int, sick: int, absent: int, unrecorded: int}
     */
    protected function countStatuses(Collection $rows, array $heldIds): array
    {
        $counts = ['present' => 0, 'permit' => 0, 'sick' => 0, 'absent' => 0, 'unrecorded' => 0];

        foreach ($heldIds as $sessionId) {
            $row = $rows->first(fn (StudentAttendance $row) => (int) $row->teaching_session_id === (int) $sessionId);

            if (!$row) {
                $counts['unrecorded']++;
                $counts['absent']++;

                continue;
            }

            $counts[$row->status->value] = ($counts[$row->status->value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Semester acuan ambang: yang diminta, aktif, atau null (pakai fallback).
     */
    protected function thresholdSemester(?int $semesterId): ?Semester
    {
        if ($semesterId) {
            return Semester::find($semesterId);
        }

        return Semester::where('status', 'active')->first();
    }

    /**
     * @param  array<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, Student>
     */
    protected function studentsByIds(array $ids): Collection
    {
        if (!$ids) {
            return collect();
        }

        return Student::with('studyProgram')
            ->whereIn('id', $ids)
            ->orderBy('student_number')
            ->get();
    }
}
