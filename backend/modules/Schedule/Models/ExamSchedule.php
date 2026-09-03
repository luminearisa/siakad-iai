<?php

namespace Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Models\AcademicClass;
use Modules\Lecturer\Models\Lecturer;

class ExamSchedule extends Model
{
    use HasFactory;

    protected $table = 'exam_schedules';

    protected $fillable = [
        'academic_class_id',
        'exam_type',
        'exam_date',
        'start_time',
        'end_time',
        'room_id',
        'proctor_lecturer_id',
        'is_announced',
        'announced_at',
        'notes',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'is_announced' => 'boolean',
        'announced_at' => 'datetime',
    ];

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function proctor(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'proctor_lecturer_id');
    }
}
