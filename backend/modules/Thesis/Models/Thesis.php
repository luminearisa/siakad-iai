<?php

namespace Modules\Thesis\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Student\Models\Student;

class Thesis extends Model
{
    use HasFactory;

    protected $table = 'theses';

    protected $fillable = [
        'student_id',
        'study_program_id',
        'start_semester_id',
        'completion_semester_id',
        'start_date',
        'submission_date',
        'completion_date',
        'status',
        'title_id',
        'title_en',
        'topic_id',
        'topic_en',
        'proposal_file_path',
        'final_file_path',
        'sk_date',
        'sk_number',
        'final_grade',
        'final_grade_letter',
    ];

    protected $casts = [
        'start_date' => 'date',
        'submission_date' => 'date',
        'completion_date' => 'date',
        'sk_date' => 'date',
        'final_grade' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'study_program_id');
    }

    public function startSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'start_semester_id');
    }

    public function completionSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'completion_semester_id');
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ThesisSupervisor::class, 'thesis_id')->orderBy('order', 'asc');
    }
}
