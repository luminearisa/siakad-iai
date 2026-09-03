<?php

namespace Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class Campus extends Model
{
    use HasFactory, Auditable;

    protected $table = 'campuses';

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'status',
    ];

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
}
