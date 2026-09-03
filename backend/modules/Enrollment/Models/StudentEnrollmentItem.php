<?php

namespace Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Enrollment\Enums\EnrollmentItemStatus;

class StudentEnrollmentItem extends Model
{
    use HasFactory, Auditable;

    protected $table = 'student_enrollment_items';

    protected $fillable = [
        'enrollment_id',
        'class_id',
        'course_id',
        'credits',
        'status',
        'finalized_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentItemStatus::class,
            'credits' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}
