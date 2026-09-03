<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Attendance\Enums\SessionStatus;
use Modules\Attendance\Enums\TeachingMethod;
use Modules\Class\Models\AcademicClass;
use Modules\Lecturer\Models\Lecturer;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Schedule\Models\Room;

class TeachingSession extends Model
{
    protected $table = 'teaching_sessions';

    protected $fillable = [
        'academic_class_id',
        'schedule_id',
        'lecturer_id',
        'meeting_number',
        'session_date',
        'start_time',
        'end_time',
        'topic',
        'notes',
        'teaching_method',
        'room_id',
        'status',
        'check_in_code',
        'check_in_expires_at',
    ];

    protected $casts = [
        'meeting_number' => 'integer',
        'session_date' => 'date',
        'teaching_method' => TeachingMethod::class,
        'status' => SessionStatus::class,
        'check_in_expires_at' => 'datetime',
    ];

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'teaching_session_id');
    }
}
