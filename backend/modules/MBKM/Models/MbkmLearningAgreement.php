<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\LearningAgreementStatus;

class MbkmLearningAgreement extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_learning_agreements';

    protected $fillable = [
        'participant_id',
        'title',
        'period_start',
        'period_end',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'approved_at',
        'approved_by',
        'locked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LearningAgreementStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MbkmLearningAgreementItem::class, 'learning_agreement_id')->orderBy('sort_order');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MbkmDocument::class, 'documentable');
    }

    public function isLocked(): bool
    {
        $status = $this->status instanceof LearningAgreementStatus
            ? $this->status
            : LearningAgreementStatus::from($this->status);

        return $status->isLocked();
    }

    public function totalCredits(): int
    {
        return (int) $this->items()->sum('credits');
    }
}
