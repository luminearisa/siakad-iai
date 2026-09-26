<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Audit\Traits\Auditable;

class MbkmCooperation extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'mbkm_cooperations';

    protected $fillable = [
        'partner_id',
        'program_id',
        'type',
        'number',
        'title',
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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(MbkmPartner::class, 'partner_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MbkmProgram::class, 'program_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MbkmDocument::class, 'documentable');
    }
}
