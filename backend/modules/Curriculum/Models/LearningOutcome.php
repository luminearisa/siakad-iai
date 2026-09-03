<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;

class LearningOutcome extends Model
{
    use HasFactory, Auditable;

    protected $table = 'learning_outcomes';

    protected $fillable = [
        'study_program_id',
        'code',
        'name',
        'category',
        'description',
        'status',
    ];

    /**
     * Get the study program associated with this CPL.
     */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /**
     * Get CPMKs mapped to this CPL.
     */
    public function courseOutcomes(): HasMany
    {
        return $this->hasMany(CourseLearningOutcome::class);
    }
}
