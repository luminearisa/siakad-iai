<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;
use Modules\Course\Models\Course;

class CourseLearningOutcome extends Model
{
    use HasFactory, Auditable;

    protected $table = 'course_learning_outcomes';

    protected $fillable = [
        'study_program_id',
        'course_id',
        'learning_outcome_id',
        'code',
        'name',
        'description',
        'status',
    ];

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function learningOutcome(): BelongsTo
    {
        return $this->belongsTo(LearningOutcome::class);
    }

    public function subOutcomes(): HasMany
    {
        return $this->hasMany(SubCourseLearningOutcome::class, 'course_learning_outcome_id');
    }
}
