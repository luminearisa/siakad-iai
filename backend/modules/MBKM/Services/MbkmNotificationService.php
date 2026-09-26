<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\Log;
use Modules\Identity\Models\User;
use Modules\Lecturer\Models\Lecturer;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\Student\Models\Student;

/**
 * Thin wrapper over Laravel's native notification channel for MBKM events.
 *
 * The repository shipped no notification system, so the module uses the
 * framework's built-in database notifications (the User model already uses the
 * Notifiable trait). Every dispatch is best-effort: a notification failure must
 * never break the academic workflow that triggered it.
 */
class MbkmNotificationService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyUser(?User $user, string $event, string $title, string $message, array $payload = []): void
    {
        if (!$user) {
            return;
        }

        try {
            $user->notify(new \Modules\MBKM\Notifications\MbkmDatabaseNotification(
                event: $event,
                title: $title,
                message: $message,
                payload: $payload,
            ));
        } catch (\Throwable $e) {
            Log::warning('MBKM notification failed', [
                'event' => $event,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyStudent(?Student $student, string $event, string $title, string $message, array $payload = []): void
    {
        $this->notifyUser($student?->user, $event, $title, $message, $payload);
    }

    public function notifyParticipantStudent(MbkmParticipant $participant, string $event, string $title, string $message, array $payload = []): void
    {
        $participant->loadMissing('student.user');
        $this->notifyStudent($participant->student, $event, $title, $message, array_merge([
            'participant_id' => $participant->id,
            'program_id' => $participant->program_id,
        ], $payload));
    }

    /**
     * Notify every internal/co supervisor lecturer of a participant.
     */
    public function notifyParticipantSupervisors(MbkmParticipant $participant, string $event, string $title, string $message, array $payload = []): void
    {
        $participant->loadMissing('supervisors.lecturer.user');

        foreach ($participant->supervisors as $supervisor) {
            $this->notifyUser(
                $supervisor->lecturer?->user,
                $event,
                $title,
                $message,
                array_merge(['participant_id' => $participant->id], $payload)
            );
        }
    }

    /**
     * Notify every user holding the given permission (e.g. all MBKM managers).
     */
    public function notifyPermissionHolders(string $permission, string $event, string $title, string $message, array $payload = []): void
    {
        $users = User::whereHas('roles.permissions', function ($q) use ($permission) {
            $q->where('name', $permission);
        })->get();

        foreach ($users as $user) {
            $this->notifyUser($user, $event, $title, $message, $payload);
        }
    }

    public function notifyLecturer(?Lecturer $lecturer, string $event, string $title, string $message, array $payload = []): void
    {
        $this->notifyUser($lecturer?->user, $event, $title, $message, $payload);
    }
}
