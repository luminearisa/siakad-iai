<?php

namespace Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Enums\DayOfWeek;
use Modules\Schedule\Enums\ScheduleStatus;

class ClassSchedule extends Model
{
    use HasFactory, Auditable;

    protected $table = 'class_schedules';

    protected $fillable = [
        'class_id',
        'room_id',
        'day_of_week',
        'start_time',
        'end_time',
        'effective_from',
        'effective_until',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'status' => ScheduleStatus::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
