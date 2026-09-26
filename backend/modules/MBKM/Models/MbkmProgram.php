<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\ProgramStatus;

class MbkmProgram extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'mbkm_programs';

    protected $fillable = [
        'program_type_id',
        'code',
        'name',
        'description',
        'organizer_type',
        'organizer_name',
        'faculty_id',
        'study_program_id',
        'semester_id',
        'registration_start_date',
        'registration_end_date',
        'start_date',
        'end_date',
        'quota',
        'target_degree_levels',
        'target_study_program_ids',
        'target_admission_years',
        'min_semester',
        'max_semester',
        'min_gpa',
        'min_credits',
        'max_recognized_credits',
        'participation_limit',
        'required_passed_course_ids',
        'location_mode',
        'requires_documents',
        'requires_learning_agreement',
        'requires_attendance',
        'requires_logbook',
        'logbook_period',
        'requires_assessment',
        'requires_final_report',
        'requires_recognition',
        'allow_public_participant_count',
        'min_attendance_percentage',
        'status',
        'requirements_text',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'target_degree_levels' => 'array',
            'target_study_program_ids' => 'array',
            'target_admission_years' => 'array',
            'required_passed_course_ids' => 'array',
            'registration_start_date' => 'date',
            'registration_end_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'min_gpa' => 'float',
            'min_attendance_percentage' => 'float',
            'quota' => 'integer',
            'min_semester' => 'integer',
            'max_semester' => 'integer',
            'min_credits' => 'integer',
            'max_recognized_credits' => 'integer',
            'participation_limit' => 'integer',
            'requires_documents' => 'boolean',
            'requires_learning_agreement' => 'boolean',
            'requires_attendance' => 'boolean',
            'requires_logbook' => 'boolean',
            'requires_assessment' => 'boolean',
            'requires_final_report' => 'boolean',
            'requires_recognition' => 'boolean',
            'allow_public_participant_count' => 'boolean',
        ];
    }

    public function programType(): BelongsTo
    {
        return $this->belongsTo(MbkmProgramType::class, 'program_type_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MbkmProgramLocation::class, 'program_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(MbkmProgramRequirement::class, 'program_id')->orderBy('sort_order');
    }

    public function cooperations(): HasMany
    {
        return $this->hasMany(MbkmCooperation::class, 'program_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(MbkmApplication::class, 'program_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MbkmParticipant::class, 'program_id');
    }

    public function selectionCriteria(): HasMany
    {
        return $this->hasMany(MbkmSelectionCriteria::class, 'program_id')->orderBy('sort_order');
    }

    public function assessmentComponents(): HasMany
    {
        return $this->hasMany(MbkmAssessmentComponent::class, 'program_id')->orderBy('sort_order');
    }

    public function recognitions(): HasMany
    {
        return $this->hasMany(MbkmRecognition::class, 'program_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MbkmDocument::class, 'documentable');
    }

    /**
     * Registration window is open (status + date range) — server-side truth for
     * "can a student still apply?".
     */
    public function isRegistrationOpen(): bool
    {
        if ($this->status !== ProgramStatus::PUBLISHED) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->registration_start_date && $today->lt($this->registration_start_date->startOfDay())) {
            return false;
        }

        if ($this->registration_end_date && $today->gt($this->registration_end_date->endOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * Participant slots already taken (quota-occupying statuses only).
     */
    public function usedQuota(): int
    {
        return $this->participants()
            ->whereIn('status', \Modules\MBKM\Enums\ParticipantStatus::occupyingQuota())
            ->count();
    }

    public function hasQuotaAvailable(int $additional = 1): bool
    {
        if ($this->quota === null) {
            return true;
        }

        return ($this->usedQuota() + $additional) <= $this->quota;
    }
}
