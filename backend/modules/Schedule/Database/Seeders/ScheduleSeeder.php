<?php

namespace Modules\Schedule\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Enums\ScheduleStatus;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Models\Room;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $r101 = Room::where('code', 'R-101')->first();
        $r102 = Room::where('code', 'R-102')->first();
        $r201 = Room::where('code', 'R-201')->first();
        $rSyariah = Room::where('code', 'R-SYARIAH-1')->first();
        $rFebi = Room::where('code', 'R-FEBI-101')->first();

        $c1 = AcademicClass::where('code', 'PAI201-A')->first();
        $c2 = AcademicClass::where('code', 'MKU101-A')->first();
        $c3 = AcademicClass::where('code', 'MKU102-A')->first();
        $c4 = AcademicClass::where('code', 'MKU103-A')->first();
        $c5 = AcademicClass::where('code', 'MKU105-A')->first();
        $c6 = AcademicClass::where('code', 'PAI203-A')->first();
        $c7 = AcademicClass::where('code', 'HKI201-A')->first();
        $c8 = AcademicClass::where('code', 'ES201-A')->first();

        // 1. Senin 08:00 - 10:30 (PAI201) in R-101
        if ($c1 && $r101) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c1->id,
                    'day_of_week' => DayOfWeek::MONDAY,
                    'start_time' => '08:00:00',
                ],
                [
                    'room_id' => $r101->id,
                    'end_time' => '10:30:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }

        // 2. Senin 10:45 - 12:15 (MKU101) in R-101
        if ($c2 && $r101) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c2->id,
                    'day_of_week' => DayOfWeek::MONDAY,
                    'start_time' => '10:45:00',
                ],
                [
                    'room_id' => $r101->id,
                    'end_time' => '12:15:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }

        // 3. Selasa 08:00 - 09:30 (MKU102) in R-102
        if ($c3 && $r102) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c3->id,
                    'day_of_week' => DayOfWeek::TUESDAY,
                    'start_time' => '08:00:00',
                ],
                [
                    'room_id' => $r102->id,
                    'end_time' => '09:30:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }

        // 4. Selasa 10:00 - 11:30 (MKU103) in R-102
        if ($c4 && $r102) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c4->id,
                    'day_of_week' => DayOfWeek::TUESDAY,
                    'start_time' => '10:00:00',
                ],
                [
                    'room_id' => $r102->id,
                    'end_time' => '11:30:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }

        // 5. Rabu 08:00 - 09:30 (MKU105) in R-201
        if ($c5 && $r201) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c5->id,
                    'day_of_week' => DayOfWeek::WEDNESDAY,
                    'start_time' => '08:00:00',
                ],
                [
                    'room_id' => $r201->id,
                    'end_time' => '09:30:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }

        // 6. Rabu 10:00 - 12:30 (PAI203) in R-101
        if ($c6 && $r101) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c6->id,
                    'day_of_week' => DayOfWeek::WEDNESDAY,
                    'start_time' => '10:00:00',
                ],
                [
                    'room_id' => $r101->id,
                    'end_time' => '12:30:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }

        // 7. Kamis 08:00 - 10:30 (HKI201) in R-SYARIAH-1
        if ($c7 && $rSyariah) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c7->id,
                    'day_of_week' => DayOfWeek::THURSDAY,
                    'start_time' => '08:00:00',
                ],
                [
                    'room_id' => $rSyariah->id,
                    'end_time' => '10:30:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }

        // 8. Kamis 10:45 - 13:15 (ES201) in R-FEBI-101
        if ($c8 && $rFebi) {
            ClassSchedule::firstOrCreate(
                [
                    'class_id' => $c8->id,
                    'day_of_week' => DayOfWeek::THURSDAY,
                    'start_time' => '10:45:00',
                ],
                [
                    'room_id' => $rFebi->id,
                    'end_time' => '13:15:00',
                    'status' => ScheduleStatus::ACTIVE,
                ]
            );
        }
    }
}
