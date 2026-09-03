<?php

namespace Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Academic\Models\Institution;
use Modules\Audit\Traits\Auditable;
use Modules\Schedule\Enums\RoomStatus;

class Room extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'rooms';

    protected $fillable = [
        'institution_id',
        'building_id',
        'code',
        'name',
        'building',
        'floor',
        'capacity',
        'location',
        'room_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'capacity' => 'integer',
            'floor' => 'integer',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function buildingModel(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }
}
