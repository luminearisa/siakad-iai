<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Models\StudyProgram;
use Modules\Audit\Traits\Auditable;

class StudentGroup extends Model
{
    use HasFactory, Auditable;

    protected $table = 'student_groups';

    protected $fillable = [
        'study_program_id',
        'name',
        'description',
        'student_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'student_count' => 'integer',
        ];
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
