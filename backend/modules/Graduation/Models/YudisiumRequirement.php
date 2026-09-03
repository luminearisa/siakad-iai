<?php

namespace Modules\Graduation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Models\StudyProgram;

class YudisiumRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_program_id',
        'name',
        'code',
        'is_document',
        'is_mandatory',
        'min_credits',
        'min_gpa',
    ];

    protected $casts = [
        'is_document' => 'boolean',
        'is_mandatory' => 'boolean',
        'min_credits' => 'integer',
        'min_gpa' => 'decimal:2',
    ];

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
