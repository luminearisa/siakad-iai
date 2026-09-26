<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\MBKM\Enums\ApprovalDecision;
use Modules\MBKM\Enums\WithdrawalType;

class MbkmWithdrawalRequest extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_withdrawal_requests';

    protected $fillable = [
        'participant_id',
        'type',
        'reason',
        'effective_date',
        'document_id',
        'status',
        'requested_by',
        'decided_at',
        'decided_by',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => WithdrawalType::class,
            'status' => ApprovalDecision::class,
            'effective_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(MbkmDocument::class, 'document_id');
    }
}
