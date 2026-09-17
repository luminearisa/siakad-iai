<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Student\Models\Student;
use Modules\Student\Resources\StudentResource;

class AttendanceService
{
    /**
     * Create teaching session and initialize attendance records for enrolled students.
     */
    public function createSession(array $data): TeachingSession
    {
        $session = TeachingSession::create($data);

        // Fetch enrolled students for this academic class
        $enrolledStudentIds = StudentEnrollmentItem::where('class_id', $session->academic_class_id)
            ->whereHas('enrollment', fn ($q) => $q->whereIn('status', ['approved', 'locked']))
            ->pluck('enrollment_id');

        $studentIds = StudentEnrollment::whereIn('id', $enrolledStudentIds)
            ->pluck('student_id');

        // Create default attendance records
        foreach ($studentIds as $studentId) {
            StudentAttendance::firstOrCreate(
                [
                    'teaching_session_id' => $session->id,
                    'student_id' => $studentId,
                ],
                [
                    'academic_class_id' => $session->academic_class_id,
                    'status' => AttendanceStatus::PRESENT,
                    'recorded_at' => now(),
                ]
            );
        }

        return $session->load(['academicClass', 'lecturer', 'room', 'attendances.student']);
    }

    /**
     * Update teaching session BAP details.
     */
    public function updateSession(TeachingSession $session, array $data): TeachingSession
    {
        $session->update($data);
        return $session->fresh(['academicClass', 'lecturer', 'room', 'attendances.student']);
    }

    /**
     * Record batch attendances for a teaching session.
     */
    public function recordBatch(TeachingSession $session, array $attendances, ?int $userId = null): void
    {
        foreach ($attendances as $att) {
            StudentAttendance::updateOrCreate(
                [
                    'teaching_session_id' => $session->id,
                    'student_id' => $att['student_id'],
                ],
                [
                    'academic_class_id' => $session->academic_class_id,
                    'status' => $att['status'],
                    'notes' => $att['notes'] ?? null,
                    'recorded_by' => $userId,
                    'recorded_at' => now(),
                ]
            );
        }
    }

    /**
     * Build the attendance sheet a lecturer sees when taking attendance.
     *
     * The sheet is derived from the *class roster* and then merged with whatever
     * attendance records already exist. Deriving from the roster (instead of only
     * from existing records) is what keeps the lecturer unblocked:
     *  - a session created before anyone enrolled still lists the current students;
     *  - students who enrolled after the session was created still appear;
     *  - unrecorded students come back with a default PRESENT status plus
     *    `is_recorded = false`, so the UI can show what still needs confirming.
     *
     * Students with a record but no longer on the roster (e.g. a cancelled KRS)
     * are appended with `is_enrolled = false` so existing history stays visible.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSessionAttendanceSheet(TeachingSession $session): array
    {
        $rosterIds = $this->enrolledStudentIds((int) $session->academic_class_id);

        $attendances = StudentAttendance::where('teaching_session_id', $session->id)
            ->with(['student.studyProgram'])
            ->get();

        $recordedByStudent = $attendances->keyBy('student_id');

        $extraIds = $attendances->pluck('student_id')
            ->diff($rosterIds)
            ->values()
            ->all();

        $students = Student::with('studyProgram')
            ->whereIn('id', array_merge($rosterIds, $extraIds))
            ->get()
            ->keyBy('id');

        $sheet = [];

        // 1. Everyone currently on the roster, ordered by NIM for easy scanning.
        foreach ($this->sortStudentsByIds($students, $rosterIds) as $student) {
            $sheet[] = $this->attendanceSheetRow(
                $session,
                $student,
                $recordedByStudent->get($student->id),
                isEnrolled: true
            );
        }

        // 2. Recorded students that dropped off the roster.
        foreach ($this->sortStudentsByIds($students, $extraIds) as $student) {
            $sheet[] = $this->attendanceSheetRow(
                $session,
                $student,
                $recordedByStudent->get($student->id),
                isEnrolled: false
            );
        }

        return $sheet;
    }

    /**
     * Student ids enrolled in a class through an approved/locked KRS.
     *
     * @return array<int, int>
     */
    protected function enrolledStudentIds(int $classId): array
    {
        $enrollmentIds = StudentEnrollmentItem::where('class_id', $classId)
            ->whereHas('enrollment', fn ($q) => $q->whereIn('status', ['approved', 'locked']))
            ->pluck('enrollment_id');

        return StudentEnrollment::whereIn('id', $enrollmentIds)
            ->pluck('student_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve the given student ids into models ordered by student number.
     *
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
     * Shape one row of the attendance sheet.
     *
     * @return array<string, mixed>
     */
    protected function attendanceSheetRow(
        TeachingSession $session,
        Student $student,
        ?StudentAttendance $attendance,
        bool $isEnrolled
    ): array {
        $status = $attendance?->status ?? AttendanceStatus::PRESENT;

        return [
            'id' => $attendance?->id,
            'teaching_session_id' => $session->id,
            'student_id' => $student->id,
            'academic_class_id' => $session->academic_class_id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_code' => $status->code(),
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
     * Open self check-in token for students with time limit.
     */
    public function openCheckIn(TeachingSession $session, int $durationMinutes = 15): TeachingSession
    {
        $code = strtoupper(Str::random(6));

        $session->update([
            'check_in_code' => $code,
            'check_in_expires_at' => now()->addMinutes($durationMinutes),
            'status' => SessionStatus::OPEN,
        ]);

        return $session;
    }

    /**
     * Close teaching session.
     */
    public function closeSession(TeachingSession $session): TeachingSession
    {
        $session->update([
            'status' => SessionStatus::CLOSED,
            'check_in_code' => null,
            'check_in_expires_at' => null,
        ]);

        return $session;
    }

    /**
     * Process student self check-in via code.
     */
    public function selfCheckIn(int $sessionId, string $code, Student $student, ?int $userId = null): StudentAttendance
    {
        $session = TeachingSession::findOrFail($sessionId);

        if (!$session->check_in_code || strtoupper($session->check_in_code) !== strtoupper(trim($code))) {
            throw new \Exception('Kode presensi tidak valid.');
        }

        if (!$session->check_in_expires_at || $session->check_in_expires_at->isPast()) {
            throw new \Exception('Sesi presensi mandiri telah berakhir atau kedaluwarsa.');
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
                'notes' => 'Presensi Mandiri (Self Check-in)',
            ]
        );

        return $attendance->load(['teachingSession', 'student']);
    }

    /**
     * Get class attendance matrix recap for meetings 1 to 16.
     */
    public function getClassRecap(AcademicClass $class): array
    {
        $sessions = TeachingSession::where('academic_class_id', $class->id)
            ->orderBy('meeting_number')
            ->get();

        $enrolledEnrollmentIds = StudentEnrollmentItem::where('class_id', $class->id)
            ->whereHas('enrollment', fn ($q) => $q->whereIn('status', ['approved', 'locked']))
            ->pluck('enrollment_id');

        $students = Student::whereIn(
            'id',
            StudentEnrollment::whereIn('id', $enrolledEnrollmentIds)->pluck('student_id')
        )->with('studyProgram')->get();

        $attendances = StudentAttendance::where('academic_class_id', $class->id)->get();

        $matrix = [];
        $totalSessions = $sessions->count();

        foreach ($students as $student) {
            $studentAtts = $attendances->where('student_id', $student->id);
            $present = $studentAtts->where('status', AttendanceStatus::PRESENT)->count();
            $permit = $studentAtts->where('status', AttendanceStatus::PERMIT)->count();
            $sick = $studentAtts->where('status', AttendanceStatus::SICK)->count();
            $absent = $studentAtts->where('status', AttendanceStatus::ABSENT)->count();

            // Total valid attendance considered for exam eligibility
            $validAttendance = $present + $permit + $sick;
            $percentage = $totalSessions > 0 ? round(($validAttendance / $totalSessions) * 100, 1) : 100.0;
            $isEligibleForExam = $percentage >= 75.0;

            $meetings = [];
            foreach ($sessions as $session) {
                $att = $studentAtts->firstWhere('teaching_session_id', $session->id);
                $meetings[$session->meeting_number] = [
                    'session_id' => $session->id,
                    'meeting_number' => $session->meeting_number,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'status' => $att ? $att->status->value : null,
                    'status_code' => $att ? $att->status->code() : '-',
                    'notes' => $att ? $att->notes : null,
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
                'present_count' => $present,
                'permit_count' => $permit,
                'sick_count' => $sick,
                'absent_count' => $absent,
                'total_meetings' => $totalSessions,
                'percentage' => $percentage,
                'is_eligible' => $isEligibleForExam,
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
            'total_sessions' => $totalSessions,
            'sessions' => $sessions,
            'recap' => $matrix,
        ];
    }

    /**
     * Get student realtime attendance breakdown across all enrolled classes in active semester.
     */
    public function getStudentRecap(Student $student, ?int $semesterId = null): array
    {
        $enrollmentQuery = \Modules\Enrollment\Models\StudentEnrollment::where('student_id', $student->id)
            ->whereIn('status', ['approved', 'locked'])
            ->with(['items.academicClass.course', 'items.academicClass.lecturers', 'items.academicClass.schedules.room', 'semester']);

        if ($semesterId) {
            $enrollmentQuery->where('semester_id', $semesterId);
        }

        $enrollments = $enrollmentQuery->get();
        $classBreakdowns = [];

        $totalClasses = 0;
        $totalSessionsAll = 0;
        $totalPresentAll = 0;
        $totalPermitAll = 0;
        $totalSickAll = 0;
        $totalAbsentAll = 0;

        foreach ($enrollments as $enr) {
            foreach ($enr->items as $item) {
                $class = $item->academicClass;
                if (!$class) continue;

                $totalClasses++;
                $sessions = TeachingSession::where('academic_class_id', $class->id)
                    ->with(['lecturer', 'room'])
                    ->orderBy('meeting_number')
                    ->get();

                $attendances = StudentAttendance::where('academic_class_id', $class->id)
                    ->where('student_id', $student->id)
                    ->get();

                $present = $attendances->where('status', AttendanceStatus::PRESENT)->count();
                $permit = $attendances->where('status', AttendanceStatus::PERMIT)->count();
                $sick = $attendances->where('status', AttendanceStatus::SICK)->count();
                $absent = $attendances->where('status', AttendanceStatus::ABSENT)->count();

                $totalSessions = $sessions->count();
                $totalSessionsAll += $totalSessions;
                $totalPresentAll += $present;
                $totalPermitAll += $permit;
                $totalSickAll += $sick;
                $totalAbsentAll += $absent;

                $validAttendance = $present + $permit + $sick;
                $percentage = $totalSessions > 0 ? round(($validAttendance / $totalSessions) * 100, 1) : 100.0;
                $isEligible = $percentage >= 75.0;

                $meetingsDetail = [];
                foreach ($sessions as $session) {
                    $att = $attendances->firstWhere('teaching_session_id', $session->id);
                    $meetingsDetail[] = [
                        'session_id' => $session->id,
                        'meeting_number' => $session->meeting_number,
                        'session_date' => $session->session_date ? $session->session_date->format('Y-m-d') : null,
                        'start_time' => $session->start_time ? substr($session->start_time, 0, 5) : null,
                        'end_time' => $session->end_time ? substr($session->end_time, 0, 5) : null,
                        'topic' => $session->topic,
                        'teaching_method' => $session->teaching_method?->value ?? $session->teaching_method,
                        'teaching_method_label' => $session->teaching_method?->label(),
                        'lecturer_name' => $session->lecturer?->full_name,
                        'room' => $session->room?->code,
                        'status' => $att ? $att->status->value : 'absent',
                        'status_label' => $att ? $att->status->label() : 'Belum Dicatat / Alpa',
                        'status_code' => $att ? $att->status->code() : 'A',
                        'notes' => $att ? $att->notes : null,
                        'is_check_in_active' => $session->check_in_code && $session->check_in_expires_at && $session->check_in_expires_at->isFuture(),
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
                    'semester_name' => $enr->semester?->name,
                    'lecturers' => $class->lecturers,
                    'total_sessions' => $totalSessions,
                    'present_count' => $present,
                    'permit_count' => $permit,
                    'sick_count' => $sick,
                    'absent_count' => $absent,
                    'percentage' => $percentage,
                    'is_eligible' => $isEligible,
                    'meetings' => $meetingsDetail,
                ];
            }
        }

        $overallValid = $totalPresentAll + $totalPermitAll + $totalSickAll;
        $overallPercentage = $totalSessionsAll > 0 ? round(($overallValid / $totalSessionsAll) * 100, 1) : 100.0;

        return [
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'study_program' => $student->studyProgram?->name,
            ],
            'summary' => [
                'total_classes' => $totalClasses,
                'total_sessions' => $totalSessionsAll,
                'total_present' => $totalPresentAll,
                'total_permit' => $totalPermitAll,
                'total_sick' => $totalSickAll,
                'total_absent' => $totalAbsentAll,
                'overall_percentage' => $overallPercentage,
                'is_eligible_overall' => $overallPercentage >= 75.0,
            ],
            'classes' => $classBreakdowns,
        ];
    }
}
