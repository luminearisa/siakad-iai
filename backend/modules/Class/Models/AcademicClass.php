<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Enums\ClassStatus;
use Modules\Course\Models\Course;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Lecturer\Models\Lecturer;
use Modules\Schedule\Models\ClassSchedule;

class AcademicClass extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'academic_classes';

    protected $fillable = [
        'semester_id',
        'course_id',
        'study_program_id',
        'code',
        'name',
        'section',
        'capacity',
        'enrolled_count',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClassStatus::class,
            'capacity' => 'integer',
            'enrolled_count' => 'integer',
        ];
    }

    /**
     * Get the semester the class belongs to.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the course for this class.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the study program for this class.
     */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /**
     * Get the lecturers assigned to this class.
     */
    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class, 'class_lecturers', 'class_id', 'lecturer_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the class lecturer pivot records.
     */
    public function classLecturers(): HasMany
    {
        return $this->hasMany(ClassLecturer::class, 'class_id');
    }

    /**
     * Get schedules for this class.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'class_id');
    }

    /**
     * Get enrollment items for this class.
     */
    public function enrollmentItems(): HasMany
    {
        return $this->hasMany(StudentEnrollmentItem::class, 'class_id');
    }

    /**
     * Calculate actual active enrollment count.
     */
    public function getActiveEnrollmentCountAttribute(): int
    {
        return $this->enrollmentItems()
            ->whereHas('enrollment', function ($q) {
                $q->whereIn('status', ['submitted', 'approved', 'locked']);
            })
            ->where('status', 'enrolled')
            ->count();
    }

    /**
     * Get all actively enrolled students for this class.
     */
    public function enrolledStudents()
    {
        return \Modules\Student\Models\Student::whereHas('enrollments.items', function ($q) {
            $q->where('class_id', $this->id)
                ->whereIn('status', ['enrolled', 'approved']);
        })->get();
    }

    /**
     * Check if class has available capacity.
     */
    public function hasAvailableCapacity(): bool
    {
        return $this->enrolled_count < $this->capacity;
    }
}
