<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Course\Models\Course;

class CurriculumSubject extends Model
{
    use HasFactory, Auditable;

    protected $table = 'curriculum_subjects';

    protected $fillable = [
        'curriculum_semester_id',
        'course_id',
        'is_mandatory',
        'subject_type',
        'is_package',
        'credits_override',
        'minimum_grade',
        'prerequisites_text',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_package' => 'boolean',
            'credits_override' => 'integer',
        ];
    }

    public function curriculumSemester(): BelongsTo
    {
        return $this->belongsTo(CurriculumSemester::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get effective credits (override or course credits).
     */
    public function getCreditsAttribute(): int
    {
        return $this->credits_override ?? $this->course?->credits ?? 0;
    }
}
