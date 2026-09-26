<?php

namespace Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Models\Semester;
use Modules\Advising\Models\AdvisingSession;
use Modules\Audit\Traits\Auditable;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;

class StudentEnrollment extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'student_enrollments';

    protected $fillable = [
        'student_id',
        'semester_id',
        'status',
        'total_credits',
        'max_credits',
        'submitted_at',
        'approved_at',
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'total_credits' => 'integer',
            'max_credits' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentEnrollmentItem::class, 'enrollment_id');
    }

    /**
     * Items that are still part of the KRS.
     *
     * Batal-tambah rows (DROPPED) and rows cancelled while the KRS was a draft
     * (CANCELLED) are deliberately excluded: they stay in the database purely as
     * an audit trail and must never be counted as study load, never appear in the
     * student's schedule/KHS, and never satisfy a prerequisite.
     */
    public function activeItems(): HasMany
    {
        return $this->items()->where('status', EnrollmentItemStatus::ENROLLED->value);
    }

    /**
     * Items that were dropped or cancelled (batal-tambah trail).
     */
    public function inactiveItems(): HasMany
    {
        return $this->items()->whereIn('status', [
            EnrollmentItemStatus::DROPPED->value,
            EnrollmentItemStatus::CANCELLED->value,
        ]);
    }

    /**
     * Workflow audit trail of this KRS.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(EnrollmentStatusHistory::class, 'enrollment_id')->orderBy('id');
    }

    public function advisingSessions(): HasMany
    {
        return $this->hasMany(AdvisingSession::class, 'enrollment_id');
    }

    /**
     * Recalculate and update the total credits cached on this enrollment.
     *
     * Only actively enrolled items count — dropped/cancelled rows are history.
     */
    public function recalculateCredits(): int
    {
        $total = (int) $this->items()->where('status', EnrollmentItemStatus::ENROLLED->value)->sum('credits');
        $this->total_credits = $total;
        $this->saveQuietly();

        return $total;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [EnrollmentStatus::DRAFT, EnrollmentStatus::REVISION_REQUIRED]);
    }
}
