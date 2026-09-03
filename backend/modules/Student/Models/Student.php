<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\StudentStatus;

class Student extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'study_program_id',
        'student_number',
        'national_student_number',
        'national_id',
        'mother_name',
        'full_name',
        'nickname',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'marital_status',
        'phone',
        'email',
        'address',
        'province',
        'city',
        'district',
        'postal_code',
        'status',
        'admission_year',
        'entry_date',
        'graduation_date',
        'photo_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'birth_date' => 'date',
            'entry_date' => 'date',
            'graduation_date' => 'date',
            'admission_year' => 'integer',
        ];
    }

    /**
     * Get the user account for the student.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the study program of the student.
     */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /**
     * Get the family details for the student.
     */
    public function families(): HasMany
    {
        return $this->hasMany(StudentFamily::class);
    }

    /**
     * Get the education history for the student.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(StudentEducation::class);
    }

    /**
     * Get enrollments (KRS) for the student.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(\Modules\Enrollment\Models\StudentEnrollment::class, 'student_id');
    }

    /**
     * Get active academic advisor for the student.
     */
    public function academicAdvisor(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\Modules\Advising\Models\AcademicAdvisor::class, 'student_id')->where('status', 'active');
    }
}
