<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;
use Modules\Curriculum\Enums\CurriculumStatus;

class Curriculum extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'curricula';

    protected $fillable = [
        'study_program_id',
        'curriculum_year_id',
        'credit_limit_id',
        'grade_scale_id',
        'code',
        'name',
        'version',
        'description',
        'start_year',
        'end_year',
        'status',
        'effective_date',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => CurriculumStatus::class,
            'start_year' => 'integer',
            'end_year' => 'integer',
            'effective_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Get the study program associated with the curriculum.
     */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function curriculumYear(): BelongsTo
    {
        return $this->belongsTo(CurriculumYear::class);
    }

    public function creditLimit(): BelongsTo
    {
        return $this->belongsTo(CreditLimit::class);
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    /**
     * Get the semesters for the curriculum.
     */
    public function semesters(): HasMany
    {
        return $this->hasMany(CurriculumSemester::class)->orderBy('semester_number', 'asc');
    }

    /**
     * Get all subjects across all semesters in this curriculum.
     */
    public function subjects(): HasManyThrough
    {
        return $this->hasManyThrough(
            CurriculumSubject::class,
            CurriculumSemester::class,
            'curriculum_id',
            'curriculum_semester_id'
        );
    }

    /**
     * Calculate total credits across all subjects in the curriculum.
     */
    public function getTotalCreditsAttribute(): int
    {
        return $this->subjects->sum(function (CurriculumSubject $subject) {
            return $subject->credits;
        });
    }
}
