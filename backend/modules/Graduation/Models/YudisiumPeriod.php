<?php

namespace Modules\Graduation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Models\Semester;

class YudisiumPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'name',
        'registration_start_date',
        'registration_end_date',
        'yudisium_date',
        'quota',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'registration_start_date' => 'date:Y-m-d',
        'registration_end_date' => 'date:Y-m-d',
        'yudisium_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'quota' => 'integer',
    ];

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(YudisiumParticipant::class);
    }
}
