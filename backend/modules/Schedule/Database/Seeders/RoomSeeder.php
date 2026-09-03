<?php

namespace Modules\Schedule\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Models\Institution;
use Modules\Schedule\Enums\RoomStatus;
use Modules\Schedule\Models\Room;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();

        $rooms = [
            [
                'code' => 'R-101',
                'name' => 'Ruang Kuliah Teori 101',
                'building' => 'Gedung Tarbiyah A',
                'floor' => 1,
                'capacity' => 45,
                'room_type' => 'classroom',
                'status' => RoomStatus::ACTIVE,
            ],
            [
                'code' => 'R-102',
                'name' => 'Ruang Kuliah Teori 102',
                'building' => 'Gedung Tarbiyah A',
                'floor' => 1,
                'capacity' => 45,
                'room_type' => 'classroom',
                'status' => RoomStatus::ACTIVE,
            ],
            [
                'code' => 'R-201',
                'name' => 'Ruang Kuliah Teori 201',
                'building' => 'Gedung Tarbiyah A',
                'floor' => 2,
                'capacity' => 40,
                'room_type' => 'classroom',
                'status' => RoomStatus::ACTIVE,
            ],
            [
                'code' => 'LAB-MICRO',
                'name' => 'Laboratorium Microteaching Terpadu',
                'building' => 'Gedung Laboratorium Terpadu',
                'floor' => 2,
                'capacity' => 25,
                'room_type' => 'laboratory',
                'status' => RoomStatus::ACTIVE,
            ],
            [
                'code' => 'LAB-KOMP',
                'name' => 'Laboratorium Komputer & CBT',
                'building' => 'Gedung Laboratorium Terpadu',
                'floor' => 3,
                'capacity' => 50,
                'room_type' => 'laboratory',
                'status' => RoomStatus::ACTIVE,
            ],
            [
                'code' => 'R-SYARIAH-1',
                'name' => 'Ruang Peradilan Semu & Moot Court',
                'building' => 'Gedung Syariah B',
                'floor' => 1,
                'capacity' => 35,
                'room_type' => 'classroom',
                'status' => RoomStatus::ACTIVE,
            ],
            [
                'code' => 'R-FEBI-101',
                'name' => 'Ruang Kuliah FEBI 101 & Mini Bank Syariah',
                'building' => 'Gedung FEBI C',
                'floor' => 1,
                'capacity' => 40,
                'room_type' => 'classroom',
                'status' => RoomStatus::ACTIVE,
            ],
            [
                'code' => 'AUD-UTAMA',
                'name' => 'Auditorium Utama KH. Hasyim Asy\'ari',
                'building' => 'Gedung Rektorat Terpadu',
                'floor' => 3,
                'capacity' => 350,
                'room_type' => 'auditorium',
                'status' => RoomStatus::ACTIVE,
            ],
        ];

        foreach ($rooms as $r) {
            Room::firstOrCreate(
                ['code' => $r['code']],
                array_merge($r, ['institution_id' => $institution?->id])
            );
        }
    }
}
