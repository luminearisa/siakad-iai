<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class CurriculumSemester extends Model
{
    use HasFactory, Auditable;

    protected $table = 'curriculum_semesters';

    protected $fillable = [
        'curriculum_id',
        'semester_number',
        'name',
        'recommended_credits',
    ];

    protected function casts(): array
    {
        return [
            'semester_number' => 'integer',
            'recommended_credits' => 'integer',
        ];
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(CurriculumSubject::class);
    }
}
