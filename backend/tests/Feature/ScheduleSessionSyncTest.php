<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Semester;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Lecturer\Models\Lecturer;
use Modules\Schedule\Actions\CreateScheduleAction;
use Modules\Schedule\Actions\SyncClassSessionsAction;
use Modules\Schedule\Actions\UpdateScheduleAction;
use Modules\Schedule\Models\Room;
use Modules\Student\Models\Student;
use Tests\TestCase;

/**
 * Sesi perkuliahan harus dibangun dari SELURUH jadwal kelas dengan penomoran
 * global, memakai total_teaching_weeks semester, dan tidak pernah menimpa sesi
 * yang sudah berlangsung atau sudah punya presensi.
 */
class ScheduleSessionSyncTest extends TestCase
{
    use RefreshDatabase;

    private Lecturer $lecturer;

    private Course $course;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->lecturer = Lecturer::firstOrFail();
        $this->course = Course::firstOrFail();
        $this->room = Room::where('code', 'R-101')->firstOrFail();
    }

    public function test_two_schedules_in_one_week_get_unique_global_meeting_numbers(): void
    {
        $lectureStart = $this->nextMonday();
        $class = $this->makeClass($this->makeSemester(8, 'dua jadwal'));
        $class->lecturers()->attach($this->lecturer->id, ['role' => 'primary']);

        $monday = app(CreateScheduleAction::class)->execute($this->schedulePayload($class, 'monday', '08:00:00', '10:30:00'));
        $tuesday = app(CreateScheduleAction::class)->execute($this->schedulePayload($class, 'tuesday', '09:00:00', '11:00:00'));

        $sessions = TeachingSession::where('academic_class_id', $class->id)
            ->orderBy('meeting_number')
            ->get();

        // 2 jadwal x 8 minggu = 16 pertemuan bernomor 1..16, bukan dua rangkaian 1..8.
        $this->assertCount(16, $sessions);
        $this->assertSame(range(1, 16), $sessions->pluck('meeting_number')->all());
        $this->assertSame(16, $sessions->pluck('session_date')->map->toDateString()->unique()->count());
        $this->assertSame([$this->lecturer->id], $sessions->pluck('lecturer_id')->unique()->all());

        $this->assertSame(8, $sessions->where('schedule_id', $monday->id)->count());
        $this->assertSame(8, $sessions->where('schedule_id', $tuesday->id)->count());

        // Urutan global: Senin pekan 1, Selasa pekan 1, Senin pekan 2, dst.
        $this->assertSame($lectureStart->toDateString(), $sessions[0]->session_date->toDateString());
        $this->assertSame($monday->id, $sessions[0]->schedule_id);
        $this->assertSame($lectureStart->copy()->addDay()->toDateString(), $sessions[1]->session_date->toDateString());
        $this->assertSame($tuesday->id, $sessions[1]->schedule_id);
        $this->assertSame($lectureStart->copy()->addWeek()->toDateString(), $sessions[2]->session_date->toDateString());
        $this->assertSame($monday->id, $sessions[2]->schedule_id);

        // Sesi milik jadwal kedua tidak menimpa tanggal/jam/ruangan sesi jadwal pertama.
        $this->assertSame('08:00:00', $sessions[0]->start_time);
        $this->assertSame('09:00:00', $sessions[1]->start_time);
        $this->assertSame($this->room->id, $sessions[0]->room_id);
    }

    public function test_session_count_follows_semester_total_teaching_weeks(): void
    {
        $class = $this->makeClass($this->makeSemester(14, 'empat belas minggu'));
        $class->lecturers()->attach($this->lecturer->id, ['role' => 'primary']);

        app(CreateScheduleAction::class)->execute($this->schedulePayload($class, 'monday', '08:00:00', '10:30:00'));

        $sessions = TeachingSession::where('academic_class_id', $class->id)->orderBy('meeting_number')->get();

        $this->assertCount(14, $sessions);
        $this->assertSame(range(1, 14), $sessions->pluck('meeting_number')->all());
        $this->assertSame(14, $sessions->pluck('session_date')->map->toDateString()->unique()->count());
    }

    public function test_session_with_recorded_attendance_is_not_moved_when_schedule_changes(): void
    {
        $class = $this->makeClass($this->makeSemester(8, 'presensi terkunci'));
        $class->lecturers()->attach($this->lecturer->id, ['role' => 'primary']);

        $schedule = app(CreateScheduleAction::class)->execute($this->schedulePayload($class, 'monday', '08:00:00', '10:30:00'));

        $sessions = TeachingSession::where('academic_class_id', $class->id)->orderBy('meeting_number')->get();
        $recorded = $sessions->firstWhere('meeting_number', 3);
        $untouched = $sessions->firstWhere('meeting_number', 5);

        StudentAttendance::create([
            'teaching_session_id' => $recorded->id,
            'student_id' => Student::firstOrFail()->id,
            'academic_class_id' => $class->id,
            'status' => AttendanceStatus::PRESENT,
            'recorded_at' => now(),
        ]);

        $recordedDate = $recorded->session_date->toDateString();
        $otherDate = $untouched->session_date->toDateString();

        // Jadwal pindah hari: sesi berikutnya ikut bergeser, sesi berpresensi tidak.
        app(UpdateScheduleAction::class)->execute($schedule, [
            'day_of_week' => 'thursday',
            'start_time' => '13:00:00',
            'end_time' => '15:30:00',
        ]);

        $this->assertSame($recordedDate, $recorded->fresh()->session_date->toDateString());
        $this->assertSame('08:00:00', $recorded->fresh()->start_time);
        $this->assertNotSame($otherDate, $untouched->fresh()->session_date->toDateString());
        $this->assertSame('13:00:00', $untouched->fresh()->start_time);
        $this->assertSame(8, TeachingSession::where('academic_class_id', $class->id)->count());
    }

    public function test_class_without_assigned_lecturer_creates_no_sessions(): void
    {
        $class = $this->makeClass($this->makeSemester(8, 'tanpa pengampu'));

        app(CreateScheduleAction::class)->execute($this->schedulePayload($class, 'monday', '08:00:00', '10:30:00'));

        $this->assertSame(0, TeachingSession::where('academic_class_id', $class->id)->count());
        $this->assertSame(0, app(SyncClassSessionsAction::class)->syncClass($class->fresh()));
    }

    public function test_sync_is_idempotent(): void
    {
        $class = $this->makeClass($this->makeSemester(8, 'idempoten'));
        $class->lecturers()->attach($this->lecturer->id, ['role' => 'primary']);

        $schedule = app(CreateScheduleAction::class)->execute($this->schedulePayload($class, 'monday', '08:00:00', '10:30:00'));

        $sync = app(SyncClassSessionsAction::class);
        $this->assertSame(0, $sync->syncSchedule($schedule));
        $this->assertSame(8, TeachingSession::where('academic_class_id', $class->id)->count());
    }

    private function schedulePayload(AcademicClass $class, string $day, string $start, string $end): array
    {
        return [
            'class_id' => $class->id,
            'room_id' => $this->room->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'active',
        ];
    }

    /** Pekan kuliah dimulai Senin depan supaya semua sesi ber tanggal depan. */
    private function nextMonday(): Carbon
    {
        return Carbon::now()->addWeeks(2)->startOfDay()->next(Carbon::MONDAY);
    }

    private function makeSemester(int $weeks, string $suffix): Semester
    {
        $start = $this->nextMonday();

        return Semester::create([
            'academic_year_id' => AcademicYear::firstOrFail()->id,
            'name' => 'Sync '.$suffix,
            'type' => 'ganjil',
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addWeeks($weeks + 6)->toDateString(),
            'lecture_start_date' => $start->toDateString(),
            'lecture_end_date' => $start->copy()->addWeeks($weeks + 2)->toDateString(),
            'total_teaching_weeks' => $weeks,
            'status' => 'inactive',
        ]);
    }

    private function makeClass(Semester $semester): AcademicClass
    {
        return AcademicClass::create([
            'semester_id' => $semester->id,
            'course_id' => $this->course->id,
            'study_program_id' => $this->course->study_program_id,
            'code' => 'SYNC-'.$semester->id,
            'name' => 'Kelas Uji Sinkronisasi Sesi',
            'section' => 'A',
            'status' => 'open',
        ]);
    }
}
