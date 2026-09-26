<?php

namespace Modules\MBKM\Services;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\SupervisorRole;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmPlacement;
use Modules\MBKM\Models\MbkmProgramLocation;
use Modules\MBKM\Models\MbkmSupervisor;

/**
 * Placement (partner/location/division) and supervisor assignment.
 *
 * Placement lives at participant level, not program level, so one program with
 * many partners and locations can place each participant differently.
 */
class MbkmPlacementService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
    ) {}

    /**
     * Create or update the placement of a participant.
     */
    public function upsertPlacement(MbkmParticipant $participant, array $data, User $actor): MbkmPlacement
    {
        // The chosen location must belong to the participant's program.
        if (!empty($data['location_id'])) {
            $belongsToProgram = MbkmProgramLocation::whereKey($data['location_id'])
                ->where('program_id', $participant->program_id)
                ->exists();

            if (!$belongsToProgram) {
                throw ValidationException::withMessages([
                    'location_id' => ['Lokasi tidak berasal dari program peserta ini.'],
                ]);
            }
        }

        $placement = MbkmPlacement::firstOrNew(['participant_id' => $participant->id]);
        $isNew = !$placement->exists;

        $placement->fill([
            'partner_id' => $data['partner_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'division' => $data['division'] ?? null,
            'position' => $data['position'] ?? null,
            'batch' => $data['batch'] ?? null,
            'field_supervisor_name' => $data['field_supervisor_name'] ?? null,
            'field_supervisor_position' => $data['field_supervisor_position'] ?? null,
            'field_supervisor_email' => $data['field_supervisor_email'] ?? null,
            'field_supervisor_phone' => $data['field_supervisor_phone'] ?? null,
            'field_supervisor_organization' => $data['field_supervisor_organization'] ?? null,
            'start_date' => $data['start_date'] ?? $placement->start_date ?? $participant->start_date,
            'end_date' => $data['end_date'] ?? $placement->end_date ?? $participant->end_date,
            'status' => $data['status'] ?? $placement->status ?? 'active',
            'notes' => $data['notes'] ?? $placement->notes,
            'created_by' => $placement->created_by ?? $actor->id,
        ]);

        $placement->save();

        $this->historyService->record(
            entity: $participant,
            action: $isNew ? 'placement.created' : 'placement.updated',
            notes: 'Penempatan peserta diperbarui.',
            meta: [
                'partner_id' => $placement->partner_id,
                'location_id' => $placement->location_id,
                'division' => $placement->division,
            ],
            actorId: $actor->id
        );

        return $placement->fresh(['partner', 'location']);
    }

    /**
     * Assign a supervisor (internal lecturer, co-supervisor, or field supervisor).
     */
    public function assignSupervisor(MbkmParticipant $participant, array $data, User $actor): MbkmSupervisor
    {
        $role = SupervisorRole::from($data['role']);

        if ($role === SupervisorRole::FIELD && empty($data['external_name'])) {
            throw ValidationException::withMessages([
                'external_name' => ['Nama pembimbing lapangan wajib diisi.'],
            ]);
        }

        if ($role !== SupervisorRole::FIELD && empty($data['lecturer_id'])) {
            throw ValidationException::withMessages([
                'lecturer_id' => ['Dosen pembimbing wajib dipilih.'],
            ]);
        }

        $supervisor = MbkmSupervisor::updateOrCreate(
            [
                'participant_id' => $participant->id,
                'role' => $role,
                'lecturer_id' => $role === SupervisorRole::FIELD ? null : $data['lecturer_id'],
            ],
            [
                'external_name' => $data['external_name'] ?? null,
                'external_position' => $data['external_position'] ?? null,
                'external_email' => $data['external_email'] ?? null,
                'external_phone' => $data['external_phone'] ?? null,
                'external_organization' => $data['external_organization'] ?? null,
                'assigned_at' => now(),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'assigned_by' => $actor->id,
            ]
        );

        $this->historyService->record(
            entity: $participant,
            action: 'supervisor.assigned',
            notes: 'Pembimbing ditetapkan (' . $role->value . ').',
            meta: [
                'supervisor_id' => $supervisor->id,
                'lecturer_id' => $supervisor->lecturer_id,
                'external_name' => $supervisor->external_name,
            ],
            actorId: $actor->id
        );

        $supervisor->loadMissing('lecturer.user');
        $this->notificationService->notifyLecturer(
            $supervisor->lecturer,
            'supervisor.assigned',
            'Penugasan Pembimbing MBKM',
            'Anda ditetapkan sebagai pembimbing ' . ($participant->student?->full_name ?? 'peserta MBKM') . '.',
            ['participant_id' => $participant->id]
        );

        return $supervisor->fresh(['lecturer']);
    }

    public function removeSupervisor(MbkmParticipant $participant, MbkmSupervisor $supervisor, User $actor): void
    {
        if ((int) $supervisor->participant_id !== (int) $participant->id) {
            throw ValidationException::withMessages([
                'supervisor' => ['Pembimbing tidak terkait dengan peserta ini.'],
            ]);
        }

        $supervisor->delete();

        $this->historyService->record(
            entity: $participant,
            action: 'supervisor.removed',
            notes: 'Penugasan pembimbing dihapus.',
            meta: ['supervisor_id' => $supervisor->id],
            actorId: $actor->id
        );
    }
}
