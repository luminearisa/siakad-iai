<?php

namespace Modules\Lecturer\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;

class CreateLecturerAction
{
    /**
     * Sandi sementara yang dibuat otomatis untuk akun baru, dikembalikan sekali ke
     * operator lewat `meta.generated_password` pada respons.
     */
    public ?string $generatedPassword = null;

    public function execute(array $data): Lecturer
    {
        $lecturerData = collect($data)->except(['educations', 'expertises'])->toArray();

        if (!isset($lecturerData['status'])) {
            $lecturerData['status'] = LecturerStatus::ACTIVE;
        }

        // Dosen yang diberi email langsung mendapat akun portal. Sandinya diacak, bukan
        // nilai default yang diketahui siapa pun, dan wajib diganti saat login pertama.
        if (empty($lecturerData['user_id']) && !empty($lecturerData['email'])) {
            $user = $this->provisionAccount(
                email: $lecturerData['email'],
                fullName: $lecturerData['full_name'] ?? 'Dosen'
            );

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

    /**
     * @throws ValidationException bila email sudah dipakai akun atau dosen lain
     */
    private function provisionAccount(string $email, string $fullName): User
    {
        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->assertCanAttach($existing, $email);

            if (! $existing->hasRole('dosen')) {
                $existing->assignRole('dosen');
            }

            // Kata sandi akun yang sudah ada sengaja tidak disentuh: pembuatan data dosen
            // tidak boleh menjadi cara mengubah kredensial akun orang lain.
            return $existing;
        }

        $this->generatedPassword = Str::password(12);

        $user = User::create([
            'name' => $fullName,
            'email' => $email,
            'password' => Hash::make($this->generatedPassword),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => true,
        ]);

        $user->assignRole('dosen');

        return $user;
    }

    private function assertCanAttach(User $user, string $email): void
    {
        $linkedLecturerId = Lecturer::where('user_id', $user->id)->value('id');

        if ($linkedLecturerId) {
            throw ValidationException::withMessages([
                'email' => ["Email {$email} sudah terhubung ke data dosen lain."],
            ]);
        }

        $foreignRoles = $user->roles()->pluck('name')
            ->reject(fn (string $name) => $name === 'dosen')
            ->values();

        if ($foreignRoles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'email' => [
                    "Email {$email} sudah dipakai akun dengan peran " . $foreignRoles->implode(', ')
                    . '. Gunakan email lain untuk dosen ini.',
                ],
            ]);
        }
    }
}
