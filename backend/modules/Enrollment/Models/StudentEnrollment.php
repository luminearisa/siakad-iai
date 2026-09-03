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

    public function advisingSessions(): HasMany
    {
        return $this->hasMany(AdvisingSession::class, 'enrollment_id');
    }

    /**
     * Recalculate and update the total credits cached on this enrollment.
     */
    public function recalculateCredits(): int
    {
        $total = (int) $this->items()->where('status', 'enrolled')->sum('credits');
        $this->total_credits = $total;
        $this->saveQuietly();

        return $total;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [EnrollmentStatus::DRAFT, EnrollmentStatus::REVISION_REQUIRED]);
    }
}
