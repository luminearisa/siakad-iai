<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CourseSurveyTemplate extends Model
{
    use HasFactory;

    protected $table = 'course_survey_templates';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all topics (judul) for this template.
     */
    public function topics(): HasMany
    {
        return $this->hasMany(CourseSurveyTopic::class, 'survey_template_id')->orderBy('order_number');
    }

    /**
     * Get all questions across all topics for this template.
     */
    public function questions(): HasManyThrough
    {
        return $this->hasManyThrough(
            CourseSurveyQuestion::class,
            CourseSurveyTopic::class,
            'survey_template_id', // Foreign key on topics table
            'topic_id',           // Foreign key on questions table
            'id',                  // Local key on templates table
            'id'                   // Local key on topics table
        )->orderBy('order_number');
    }

    /**
     * Get courses assigned to this survey template.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_survey_assignments',
            'survey_template_id',
            'course_id'
        )->withTimestamps();
    }
}
