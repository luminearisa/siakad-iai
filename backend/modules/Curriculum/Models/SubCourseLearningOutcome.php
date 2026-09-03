<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class SubCourseLearningOutcome extends Model
{
    use HasFactory, Auditable;

    protected $table = 'sub_course_learning_outcomes';

    protected $fillable = [
        'course_learning_outcome_id',
        'code',
        'name',
        'description',
        'status',
    ];

    public function courseOutcome(): BelongsTo
    {
        return $this->belongsTo(CourseLearningOutcome::class, 'course_learning_outcome_id');
    }
}
