<?php

namespace Modules\MBKM\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\MBKM\Models\MbkmStatusHistory;

/**
 * Records every MBKM status transition into `mbkm_status_histories`.
 *
 * This is the workflow audit trail: it answers "who moved this record where, and
 * why" for applications, participants, logbooks, assessments, recognitions and
 * every other entity in the module.
 */
class MbkmHistoryService
{
    public function record(
        Model $entity,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $notes = null,
        array $meta = [],
        ?int $actorId = null
    ): MbkmStatusHistory {
        return MbkmStatusHistory::create([
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'action' => $action,
            'from_status' => $this->normalize($fromStatus),
            'to_status' => $this->normalize($toStatus),
            'actor_id' => $actorId ?? Auth::id(),
            'notes' => $notes,
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * History entries for a given entity, newest first.
     */
    public function forEntity(Model $entity)
    {
        return MbkmStatusHistory::where('entity_type', get_class($entity))
            ->where('entity_id', $entity->getKey())
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->get();
    }

    protected function normalize(mixed $status): ?string
    {
        if ($status === null) {
            return null;
        }

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return (string) $status;
    }
}
