<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class MbkmProgramLocation extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_program_locations';

    protected $fillable = [
        'program_id',
        'name',
        'location_mode',
        'address',
        'city',
        'province',
        'country',
        'is_remote',
        'latitude',
        'longitude',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_remote' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MbkmProgram::class, 'program_id');
    }
}
