<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assessment\Enums\ComponentType;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Models\AcademicClass;

class AssessmentComponent extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'academic_class_id',
        'name',
        'code',
        'type',
        'max_score',
        'is_required',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'type' => ComponentType::class,
            'max_score' => 'decimal:2',
            'is_required' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    /**
     * Get the academic class.
     */
    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    /**
     * Scheme items using this component.
     */
    public function schemeItems(): HasMany
    {
        return $this->hasMany(AssessmentSchemeItem::class, 'assessment_component_id');
    }

    /**
     * Student grades recorded for this component.
     */
    public function grades(): HasMany
    {
        return $this->hasMany(StudentGrade::class, 'assessment_component_id');
    }
}
