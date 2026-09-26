<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\Lecturer\Models\Lecturer;

class MbkmAssessment extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_assessments';

    protected $fillable = [
        'participant_id',
        'component_id',
        'assessor_type',
        'assessor_user_id',
        'assessor_lecturer_id',
        'assessor_name',
        'score',
        'max_score',
        'feedback',
        'recommendation',
        'status',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'max_score' => 'float',
            'assessed_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(MbkmAssessmentComponent::class, 'component_id');
    }

    public function assessorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_user_id');
    }

    public function assessorLecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'assessor_lecturer_id');
    }
}
