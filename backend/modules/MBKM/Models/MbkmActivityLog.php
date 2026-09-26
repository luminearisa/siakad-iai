<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\LogbookStatus;

/**
 * Activity log / logbook entry. Can be recorded daily, weekly, or periodically
 * depending on the program's `logbook_period` policy.
 */
class MbkmActivityLog extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_activity_logs';

    protected $fillable = [
        'participant_id',
        'activity_plan_id',
        'log_date',
        'period_label',
        'activity',
        'description',
        'duration_hours',
        'output',
        'location',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'revision_count',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LogbookStatus::class,
            'log_date' => 'date',
            'duration_hours' => 'float',
            'revision_count' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function activityPlan(): BelongsTo
    {
        return $this->belongsTo(MbkmActivityPlan::class, 'activity_plan_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MbkmDocument::class, 'documentable');
    }

    public function isFinalized(): bool
    {
        $status = $this->status instanceof LogbookStatus ? $this->status : LogbookStatus::from($this->status);

        return $status->isFinalized();
    }
}
