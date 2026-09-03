<?php

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class StudyProgramSetting extends Model
{
    use HasFactory, Auditable;

    protected $table = 'study_program_settings';

    protected $fillable = [
        'study_program_id',
        'min_gpa_graduation',
        'min_final_exam_guidance',
        'final_exam_advisors_count',
        'final_exam_examiners_count',
        'is_thesis_required',
    ];

    protected function casts(): array
    {
        return [
            'min_gpa_graduation' => 'decimal:2',
            'min_final_exam_guidance' => 'integer',
            'final_exam_advisors_count' => 'integer',
            'final_exam_examiners_count' => 'integer',
            'is_thesis_required' => 'boolean',
        ];
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
