<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\Student\Models\Student;

class MbkmApplication extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_applications';

    protected $fillable = [
        'registration_number',
        'program_id',
        'student_id',
        'motivation_statement',
        'notes',
        'status',
        'submitted_at',
        'verified_at',
        'verified_by',
        'verification_notes',
        'selection_score',
        'selection_rank',
        'decided_at',
        'decided_by',
        'decision_notes',
        'withdrawn_at',
        'withdrawal_reason',
        'academic_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'decided_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'selection_score' => 'float',
            'selection_rank' => 'integer',
            'academic_snapshot' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MbkmProgram::class, 'program_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(MbkmSelectionScore::class, 'application_id');
    }

    public function participant(): HasOne
    {
        return $this->hasOne(MbkmParticipant::class, 'application_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MbkmDocument::class, 'documentable');
    }

    public function isActive(): bool
    {
        $value = $this->status instanceof ApplicationStatus ? $this->status->value : $this->status;

        return in_array($value, ApplicationStatus::activeStatuses(), true);
    }
}
