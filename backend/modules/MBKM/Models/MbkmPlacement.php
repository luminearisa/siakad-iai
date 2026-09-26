<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class MbkmPlacement extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_placements';

    protected $fillable = [
        'participant_id',
        'partner_id',
        'location_id',
        'division',
        'position',
        'batch',
        'field_supervisor_name',
        'field_supervisor_position',
        'field_supervisor_email',
        'field_supervisor_phone',
        'field_supervisor_organization',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(MbkmPartner::class, 'partner_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MbkmProgramLocation::class, 'location_id');
    }
}
