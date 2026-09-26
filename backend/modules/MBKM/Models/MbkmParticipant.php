<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\Student\Models\Student;

class MbkmParticipant extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_participants';

    protected $fillable = [
        'participant_number',
        'program_id',
        'application_id',
        'student_id',
        'start_date',
        'end_date',
        'original_end_date',
        'status',
        'final_score',
        'letter_grade',
        'grade_point',
        'recognized_credits',
        'score_finalized_at',
        'score_finalized_by',
        'completed_at',
        'terminated_at',
        'termination_reason',
        'notes',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipantStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'original_end_date' => 'date',
            'final_score' => 'float',
            'grade_point' => 'float',
            'recognized_credits' => 'integer',
            'score_finalized_at' => 'datetime',
            'completed_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MbkmProgram::class, 'program_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MbkmApplication::class, 'application_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function placement(): HasOne
    {
        return $this->hasOne(MbkmPlacement::class, 'participant_id');
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(MbkmSupervisor::class, 'participant_id');
    }

    public function internalSupervisor(): HasOne
    {
        return $this->hasOne(MbkmSupervisor::class, 'participant_id')->where('role', 'internal');
    }

    public function learningAgreement(): HasOne
    {
        return $this->hasOne(MbkmLearningAgreement::class, 'participant_id');
    }

    public function activityPlans(): HasMany
    {
        return $this->hasMany(MbkmActivityPlan::class, 'participant_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(MbkmActivityLog::class, 'participant_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MbkmAttendance::class, 'participant_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MbkmIssue::class, 'participant_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(MbkmAssessment::class, 'participant_id');
    }

    public function recognitions(): HasMany
    {
        return $this->hasMany(MbkmRecognition::class, 'participant_id');
    }

    public function completion(): HasOne
    {
        return $this->hasOne(MbkmCompletion::class, 'participant_id');
    }

    public function extensionRequests(): HasMany
    {
        return $this->hasMany(MbkmExtensionRequest::class, 'participant_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(MbkmWithdrawalRequest::class, 'participant_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MbkmDocument::class, 'documentable');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        return ParticipantStatus::isActive(
            $this->status instanceof ParticipantStatus ? $this->status : ParticipantStatus::from($this->status)
        );
    }

    /**
     * Documents that count for this participant.
     *
     * Documents uploaded during registration stay attached to the application
     * (historical record, never deleted). Documents uploaded while executing
     * the program are attached to the participant. Completion checks must look
     * at both, without duplicating or moving any row.
     *
     * @return \Illuminate\Support\Collection<int, MbkmDocument>
     */
    public function allDocuments(): Collection
    {
        $own = $this->documents()->get();

        $fromApplication = $this->application
            ? $this->application->documents()->get()
            : collect();

        return $own->concat($fromApplication);
    }

    /**
     * Whether a document of the given category has been uploaded either with
     * the application or during execution.
     *
     * @param  array<int, string>  $statuses
     */
    public function hasDocumentCategory(string $category, array $statuses = ['uploaded', 'verified']): bool
    {
        if ($this->documents()->where('category', $category)->whereIn('status', $statuses)->exists()) {
            return true;
        }

        return (bool) $this->application?->documents()
            ->where('category', $category)
            ->whereIn('status', $statuses)
            ->exists();
    }
}
