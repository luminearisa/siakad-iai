<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Assessment\Enums\SchemeStatus;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Models\AcademicClass;

class AssessmentScheme extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'academic_class_id',
        'name',
        'description',
        'status',
        'total_weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => SchemeStatus::class,
            'total_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the academic class for this assessment scheme.
     */
    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    /**
     * Get the items (components with weight) of this assessment scheme.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AssessmentSchemeItem::class, 'assessment_scheme_id');
    }

    /**
     * Get components linked through scheme items.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            AssessmentComponent::class,
            'assessment_scheme_items',
            'assessment_scheme_id',
            'assessment_component_id'
        )->withPivot(['id', 'weight'])->withTimestamps();
    }

    /**
     * Scope for active schemes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', SchemeStatus::ACTIVE);
    }

    /**
     * Recalculate total weight based on scheme items.
     */
    public function recalculateTotalWeight(): float
    {
        $total = (float) $this->items()->sum('weight');
        $this->total_weight = $total;
        $this->saveQuietly();

        return $total;
    }
}
