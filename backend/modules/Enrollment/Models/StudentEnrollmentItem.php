<?php

namespace Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Status history entries attached to this KRS row.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(EnrollmentStatusHistory::class, 'enrollment_item_id')->orderBy('id');
    }

    /**
     * Still counts towards the student's study load.
     */
    public function isActive(): bool
    {
        return $this->status === EnrollmentItemStatus::ENROLLED;
    }

    /**
     * Removed from the KRS but kept as an audit trail (batal-tambah).
     */
    public function isInactive(): bool
    {
        return in_array($this->status, [EnrollmentItemStatus::DROPPED, EnrollmentItemStatus::CANCELLED], true);
    }

    /**
     * Move this row out of the KRS without destroying it.
     *
     * A class removed while the KRS is still a draft is CANCELLED (it never became
     * official study load); a class removed from an already approved/locked KRS is
     * DROPPED, i.e. a real batal-tambah that must stay traceable.
     */
    public function transitionTo(EnrollmentItemStatus $status, bool $finalized = false): self
    {
        $this->status = $status;

        if ($finalized) {
            $this->finalized_at = now();
        }

        $this->save();

        return $this;
    }
}
