<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;

class GraduateProfile extends Model
{
    use HasFactory, Auditable;

    protected $table = 'graduate_profiles';

    protected $fillable = [
        'study_program_id',
        'code',
        'name',
        'profession',
        'description',
        'status',
    ];

    /**
     * Get the study program associated with this profile.
     */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
