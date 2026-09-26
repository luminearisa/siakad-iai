<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Lecturer\Models\Lecturer;
use Modules\MBKM\Enums\SupervisorRole;

class MbkmSupervisor extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_supervisors';

    protected $fillable = [
        'participant_id',
        'lecturer_id',
        'role',
        'external_name',
        'external_position',
        'external_email',
        'external_phone',
        'external_organization',
        'assigned_at',
        'status',
        'notes',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => SupervisorRole::class,
            'assigned_at' => 'date',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
