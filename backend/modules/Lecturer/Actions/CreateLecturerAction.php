<?php

namespace Modules\Lecturer\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Audit\Services\AuditService;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;

class CreateLecturerAction
{
    public function execute(array $data): Lecturer
    {
        $lecturerData = collect($data)->except(['educations', 'expertises'])->toArray();

        if (!isset($lecturerData['status'])) {
            $lecturerData['status'] = LecturerStatus::ACTIVE;
        }

        // Automatically provision User account with role 'dosen' if not already linked
        if (empty($lecturerData['user_id']) && !empty($lecturerData['email'])) {
            $user = User::firstOrCreate(
                ['email' => $lecturerData['email']],
                [
                    'name' => $lecturerData['full_name'] ?? 'Dosen',
                    'password' => Hash::make('password123'),
                    'status' => UserStatus::ACTIVE,
                ]
            );
            $user->assignRole('dosen');
            $lecturerData['user_id'] = $user->id;
        }

        $lecturer = Lecturer::create($lecturerData);

        if (!empty($data['educations'])) {
            foreach ($data['educations'] as $education) {
                $lecturer->educations()->create($education);
            }
        }

        if (!empty($data['expertises'])) {
            foreach ($data['expertises'] as $expertise) {
                $lecturer->expertises()->create($expertise);
            }
        }

        AuditService::log(
            action: 'created',
            module: 'Lecturer',
            description: "Lecturer {$lecturer->full_name} was created.",
            entity: $lecturer,
            oldValues: null,
            newValues: $lecturer->toArray()
        );

        return $lecturer->load(['homebaseStudyProgram.faculty', 'educations', 'expertises']);
    }
}
