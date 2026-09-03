<?php

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\SemesterType;
use Modules\Audit\Traits\Auditable;

class Semester extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'academic_year_id',
        'name',
        'type',
        'start_date',
        'end_date',
        'krs_start_date',
        'krs_end_date',
        'kprs_start_date',
        'kprs_end_date',
        'lecture_start_date',
        'lecture_end_date',
        'uts_start_date',
        'uts_end_date',
        'uas_start_date',
        'uas_end_date',
        'min_attendance_uts_percentage',
        'min_attendance_uas_percentage',
        'total_teaching_weeks',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => SemesterType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'krs_start_date' => 'date',
            'krs_end_date' => 'date',
            'kprs_start_date' => 'date',
            'kprs_end_date' => 'date',
            'lecture_start_date' => 'date',
            'lecture_end_date' => 'date',
            'uts_start_date' => 'date',
            'uts_end_date' => 'date',
            'uas_start_date' => 'date',
            'uas_end_date' => 'date',
            'min_attendance_uts_percentage' => 'float',
            'min_attendance_uas_percentage' => 'float',
            'total_teaching_weeks' => 'integer',
            'status' => AcademicStatus::class,
        ];
    }

    /**
     * Get the academic year that owns the semester.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
