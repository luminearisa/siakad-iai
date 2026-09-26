<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class MbkmSelectionCriteria extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_selection_criteria';

    protected $fillable = [
        'program_id',
        'name',
        'description',
        'weight',
        'max_score',
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

    public function scores(): HasMany
    {
        return $this->hasMany(MbkmSelectionScore::class, 'criteria_id');
    }
}
