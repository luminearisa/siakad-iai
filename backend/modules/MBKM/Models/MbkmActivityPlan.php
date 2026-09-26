<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;
use Modules\MBKM\Enums\ActivityPlanStatus;

class MbkmActivityPlan extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_activity_plans';

    protected $fillable = [
        'participant_id',
        'title',
        'description',
        'target_output',
        'planned_start_date',
        'planned_end_date',
        'planned_hours',
        'location',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityPlanStatus::class,
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'planned_hours' => 'float',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MbkmActivityLog::class, 'activity_plan_id');
    }
}
