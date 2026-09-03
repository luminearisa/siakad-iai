<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Database\Seeders\AcademicSeeder;
use Modules\Advising\Database\Seeders\AdvisingSeeder;
use Modules\Assessment\Database\Seeders\AssessmentSeeder;
use Modules\Assessment\Database\Seeders\GradeSeeder;
use Modules\Attendance\Database\Seeders\AttendanceSeeder;
use Modules\Class\Database\Seeders\ClassSeeder;
use Modules\Course\Database\Seeders\CourseSeeder;
use Modules\Curriculum\Database\Seeders\CurriculumSeeder;
use Modules\Enrollment\Database\Seeders\EnrollmentSeeder;
use Modules\Identity\Database\Seeders\IdentitySeeder;
use Modules\Lecturer\Database\Seeders\LecturerSeeder;
use Modules\Schedule\Database\Seeders\RoomSeeder;
use Modules\Schedule\Database\Seeders\ScheduleSeeder;
use Modules\Settings\Database\Seeders\SettingsSeeder;
use Modules\Student\Database\Seeders\StudentSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            IdentitySeeder::class,
            AcademicSeeder::class,
            SettingsSeeder::class,
            LecturerSeeder::class,
            StudentSeeder::class,
            CourseSeeder::class,
            CurriculumSeeder::class,
            RoomSeeder::class,
            ClassSeeder::class,
            ScheduleSeeder::class,
            AdvisingSeeder::class,
            EnrollmentSeeder::class,
            AttendanceSeeder::class,
            AssessmentSeeder::class,
            GradeSeeder::class,
        ]);
    }
}
