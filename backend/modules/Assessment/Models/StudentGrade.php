<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;

class StudentGrade extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'student_id',
        'academic_class_id',
        'assessment_component_id',
        'score',
        'graded_by',
        'graded_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'status' => GradeStatus::class,
            'graded_at' => 'datetime',
        ];
    }

    /**
     * Student being graded.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Academic class.
     */
    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    /**
     * Assessment component (e.g. UTS, UAS, Tugas 1).
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }

    /**
     * User who entered or graded this score.
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Revisions history for this grade item.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(GradeRevision::class, 'student_grade_id')->orderBy('changed_at', 'desc');
    }
}
