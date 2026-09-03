<?php

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Audit\Traits\Auditable;

class Faculty extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'name_en',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AcademicStatus::class,
        ];
    }

    /**
     * Get the institution that owns the faculty.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Get the study programs for the faculty.
     */
    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }
}
