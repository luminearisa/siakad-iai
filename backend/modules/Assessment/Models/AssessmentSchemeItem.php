<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSchemeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_scheme_id',
        'assessment_component_id',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
        ];
    }

    /**
     * Parent scheme.
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(AssessmentScheme::class, 'assessment_scheme_id');
    }

    /**
     * Linked component.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }
}
