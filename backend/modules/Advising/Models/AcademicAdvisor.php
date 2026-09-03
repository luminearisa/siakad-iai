<?php

namespace Modules\Advising\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Advising\Enums\AdvisorStatus;
use Modules\Audit\Traits\Auditable;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;

class AcademicAdvisor extends Model
{
    use HasFactory, Auditable;

    protected $table = 'academic_advisors';

    protected $fillable = [
        'student_id',
        'lecturer_id',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdvisorStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
