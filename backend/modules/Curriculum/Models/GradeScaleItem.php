<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeScaleItem extends Model
{
    use HasFactory;

    protected $table = 'grade_scale_items';

    protected $fillable = [
        'grade_scale_id',
        'grade_letter',
        'grade_point',
        'min_score',
        'max_score',
        'is_whitewash',
    ];

    protected function casts(): array
    {
        return [
            'grade_point' => 'float',
            'min_score' => 'float',
            'max_score' => 'float',
            'is_whitewash' => 'boolean',
        ];
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }
}
