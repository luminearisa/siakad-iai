<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Models\Room;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected User $admin;
    protected AcademicClass $class1;
    protected AcademicClass $class2;
    protected Room $room1;
    protected Room $room2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->first();
        $this->adminToken = $this->admin->createToken('admin_token')->plainTextToken;

        $this->class1 = AcademicClass::where('code', 'PAI201-A')->first();
        $this->class2 = AcademicClass::where('code', 'MKU101-A')->first();
        $this->room1 = Room::where('code', 'R-101')->first();
        $this->room2 = Room::where('code', 'R-102')->first();
    }

    public function test_can_list_and_filter_rooms(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/rooms?search=Tarbiyah');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'code', 'name', 'building', 'capacity', 'status'],
                ],
                'meta',
            ]);
    }

    public function test_can_create_room(): void
    {
        $payload = [
            'code' => 'R-305',
            'name' => 'Ruang Teori 305',
            'building' => 'Gedung Pascasarjana',
            'floor' => 3,
            'capacity' => 50,
            'room_type' => 'classroom',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/rooms', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'data' => ['code' => 'R-305']]);

        $this->assertDatabaseHas('rooms', ['code' => 'R-305']);
    }

    public function test_can_create_non_conflicting_schedule(): void
    {
        $newClass = AcademicClass::where('code', 'HKI201-A')->first();

        $payload = [
            'class_id' => $newClass->id,
            'room_id' => $this->room2->id,
            'day_of_week' => DayOfWeek::THURSDAY->value,
            'start_time' => '13:00',
            'end_time' => '15:30',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('class_schedules', [
            'class_id' => $newClass->id,
            'day_of_week' => 'thursday',
        ]);
    }

    public function test_rejects_room_conflict(): void
    {
        // R-101 is already used on Monday 08:00 - 10:30 by PAI201
        // Attempting to schedule HKI201 on Monday 09:00 - 11:00 in R-101
        $newClass = AcademicClass::where('code', 'HKI201-A')->first();

        $payload = [
            'class_id' => $newClass->id,
            'room_id' => $this->room1->id,
            'day_of_week' => DayOfWeek::MONDAY->value,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['room_id']]);
    }

    public function test_rejects_lecturer_conflict(): void
    {
        // Lecturer 1 (Dr. Ahmad Dosen) is assigned to PAI201 (Mon 08:00 - 10:30)
        // Lecturer 1 is also assigned to MKU103.
        // Attempting to schedule MKU103 in R-102 (different room) at the same overlapping time (Mon 09:00 - 10:30).
        $mku103 = AcademicClass::where('code', 'MKU103-A')->first();

        $payload = [
            'class_id' => $mku103->id,
            'room_id' => $this->room2->id,
            'day_of_week' => DayOfWeek::MONDAY->value,
            'start_time' => '09:00',
            'end_time' => '10:30',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lecturer']]);
    }
}
