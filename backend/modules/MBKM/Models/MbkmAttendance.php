<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\MBKM\Enums\MbkmAttendanceStatus;

class MbkmAttendance extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_attendances';

    protected $fillable = [
        'participant_id',
        'attendance_date',
        'status',
        'check_in_time',
        'check_out_time',
        'duration_hours',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MbkmAttendanceStatus::class,
            'attendance_date' => 'date',
            'duration_hours' => 'float',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }
}
