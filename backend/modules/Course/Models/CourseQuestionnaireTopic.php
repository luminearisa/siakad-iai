<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseQuestionnaireTopic extends Model
{
    use HasFactory;

    protected $table = 'course_questionnaire_topics';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order_number',
        'is_active',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'order_number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CourseQuestionnaireQuestion::class, 'topic_id')->orderBy('order_number');
    }
}
