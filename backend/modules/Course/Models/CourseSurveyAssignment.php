<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSurveyAssignment extends Model
{
    use HasFactory;

    protected $table = 'course_survey_assignments';

    protected $fillable = [
        'course_id',
        'survey_template_id',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'survey_template_id' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CourseSurveyTemplate::class, 'survey_template_id');
    }
}
