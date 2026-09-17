<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Audit\Traits\Auditable;
use Modules\Course\Enums\CourseStatus;
use Modules\Course\Enums\CourseType;

class Course extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'study_program_id',
        'course_type_id',
        'course_group_id',
        'code',
        'name',
        'short_name',
        'description',
        'credits',
        'theory_credits',
        'practical_credits',
        'field_practical_credits',
        'simulation_credits',
        'seminar_credits',
        'type',
        'category',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => CourseType::class,
            'status' => CourseStatus::class,
            'credits' => 'integer',
            'theory_credits' => 'integer',
            'practical_credits' => 'integer',
            'field_practical_credits' => 'integer',
            'simulation_credits' => 'integer',
            'seminar_credits' => 'integer',
        ];
    }

    /**
     * Get the study program managing this course.
     */
    public function studyProgram(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Academic\Models\StudyProgram::class);
    }

    /**
     * Get the course type.
     */
    public function courseType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Course\Models\CourseType::class, 'course_type_id');
    }

    /**
     * Get the course group.
     */
    public function courseGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CourseGroup::class, 'course_group_id');
    }

    /**
     * Get prerequisite courses for this course.
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id'
        )->withPivot('minimum_grade')->withTimestamps();
    }

    /**
     * Get courses that require this course as prerequisite.
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_course_id',
            'course_id'
        )->withPivot('minimum_grade')->withTimestamps();
    }

    /**
     * Get curriculum subjects using this course.
     */
    public function curriculumSubjects(): HasMany
    {
        return $this->hasMany(\Modules\Curriculum\Models\CurriculumSubject::class);
    }

    /**
     * Get questionnaire topics for this course.
     */
    public function questionnaireTopics(): HasMany
    {
        return $this->hasMany(CourseQuestionnaireTopic::class)->orderBy('order_number');
    }

    /**
     * Get survey templates assigned to this course.
     */
    public function surveyTemplates(): BelongsToMany
    {
        return $this->belongsToMany(
            CourseSurveyTemplate::class,
            'course_survey_assignments',
            'course_id',
            'survey_template_id'
        )->withTimestamps();
    }
}
