<?php

namespace Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class Building extends Model
{
    use HasFactory, Auditable;

    protected $table = 'buildings';

    protected $fillable = [
        'campus_id',
        'code',
        'name',
        'address',
        'total_floors',
        'total_rooms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_floors' => 'integer',
            'total_rooms' => 'integer',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
