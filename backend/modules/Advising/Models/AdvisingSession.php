<?php

namespace Modules\Advising\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Advising\Enums\AdvisingSessionStatus;
use Modules\Audit\Traits\Auditable;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Lecturer\Models\Lecturer;
use Modules\Student\Models\Student;

class AdvisingSession extends Model
{
    use HasFactory, Auditable;

    protected $table = 'advising_sessions';

    protected $fillable = [
        'student_id',
        'lecturer_id',
        'enrollment_id',
        'session_date',
        'topic',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdvisingSessionStatus::class,
            'session_date' => 'date',
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

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }
}
