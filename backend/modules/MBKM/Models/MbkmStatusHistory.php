<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Identity\Models\User;

/**
 * Workflow audit trail for every MBKM entity: who moved which record from which
 * status to which status, and why. Complements the generic AuditService (which
 * captures raw attribute diffs).
 */
class MbkmStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'mbkm_status_histories';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
