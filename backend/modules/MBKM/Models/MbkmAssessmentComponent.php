<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

/**
 * Configurable assessment component with its weight. Weights are program data,
 * never hardcoded — the weighted final score is derived from these rows.
 */
class MbkmAssessmentComponent extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_assessment_components';

    protected $fillable = [
        'program_id',
        'code',
        'name',
        'type',
        'weight',
        'max_score',
        'assessor_type',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'max_score' => 'float',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MbkmProgram::class, 'program_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(MbkmAssessment::class, 'component_id');
    }
}
