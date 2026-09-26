<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Audit\Traits\Auditable;

class MbkmPartner extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'mbkm_partners';

    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'city',
        'province',
        'country',
        'phone',
        'email',
        'website',
        'contact_person_name',
        'contact_person_position',
        'contact_person_email',
        'contact_person_phone',
        'status',
        'notes',
    ];

    public function cooperations(): HasMany
    {
        return $this->hasMany(MbkmCooperation::class, 'partner_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(MbkmPlacement::class, 'partner_id');
    }
}
