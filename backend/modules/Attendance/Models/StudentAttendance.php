<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;

class StudentAttendance extends Model
{
    protected $table = 'student_attendances';

    protected $fillable = [
        'teaching_session_id',
        'student_id',
        'academic_class_id',
        'status',
        'notes',
        'attachment_path',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'status' => AttendanceStatus::class,
        'recorded_at' => 'datetime',
    ];

    public function teachingSession(): BelongsTo
    {
        return $this->belongsTo(TeachingSession::class, 'teaching_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
