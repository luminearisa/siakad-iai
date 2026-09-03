<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseQuestionnaireQuestion extends Model
{
    use HasFactory;

    protected $table = 'course_questionnaire_questions';

    protected $fillable = [
        'topic_id',
        'question',
        'question_type',
        'scale_min',
        'scale_max',
        'scale_min_label',
        'scale_max_label',
        'options',
        'is_required',
        'order_number',
    ];

    protected $casts = [
        'topic_id' => 'integer',
        'scale_min' => 'integer',
        'scale_max' => 'integer',
        'options' => 'array',
        'is_required' => 'boolean',
        'order_number' => 'integer',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CourseQuestionnaireTopic::class, 'topic_id');
    }
}
