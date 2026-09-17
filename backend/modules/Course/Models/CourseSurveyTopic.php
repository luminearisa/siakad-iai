<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSurveyTopic extends Model
{
    use HasFactory;

    protected $table = 'course_survey_topics';

    protected $fillable = [
        'survey_template_id',
        'title',
        'description',
        'order_number',
    ];

    protected $casts = [
        'survey_template_id' => 'integer',
        'order_number' => 'integer',
    ];

    /**
     * Get the template this topic belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CourseSurveyTemplate::class, 'survey_template_id');
    }

    /**
     * Get all questions under this topic.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CourseSurveyQuestion::class, 'topic_id')->orderBy('order_number');
    }
}
