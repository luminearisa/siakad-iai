<?php

namespace Modules\Lecturer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Student\Enums\Gender;

class Lecturer extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'homebase_study_program_id',
        'lecturer_number',
        'nidn',
        'nidk',
        'nip',
        'full_name',
        'gender',
        'birth_place',
        'birth_date',
        'academic_degree',
        'functional_position',
        'academic_advising_quota',
        'thesis_supervisor_quota',
        'thesis_examiner_quota',
        'phone',
        'email',
        'address',
        'status',
        'join_date',
        'photo_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => LecturerStatus::class,
            'birth_date' => 'date',
            'join_date' => 'date',
            'academic_advising_quota' => 'integer',
            'thesis_supervisor_quota' => 'integer',
            'thesis_examiner_quota' => 'integer',
        ];
    }

    /**
     * Get the user account associated with the lecturer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the homebase study program of the lecturer.
     */
    public function homebaseStudyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'homebase_study_program_id');
    }

    /**
     * Get the education records of the lecturer.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(LecturerEducation::class);
    }

    /**
     * Get the expertise records of the lecturer.
     */
    public function expertises(): HasMany
    {
        return $this->hasMany(LecturerExpertise::class);
    }
}
