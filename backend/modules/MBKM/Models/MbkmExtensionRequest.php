<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\MBKM\Enums\ApprovalDecision;

class MbkmExtensionRequest extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_extension_requests';

    protected $fillable = [
        'participant_id',
        'old_end_date',
        'new_end_date',
        'reason',
        'status',
        'requested_by',
        'decided_at',
        'decided_by',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalDecision::class,
            'old_end_date' => 'date',
            'new_end_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }
}
