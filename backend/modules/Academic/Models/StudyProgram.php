<?php

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\DegreeLevel;
use Modules\Audit\Traits\Auditable;

class StudyProgram extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'faculty_id',
        'code',
        'short_name',
        'name',
        'degree',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'degree' => DegreeLevel::class,
            'status' => AcademicStatus::class,
        ];
    }

    /**
     * Get the faculty that owns the study program.
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * Get the setting for the study program.
     */
    public function setting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudyProgramSetting::class);
    }
}
