<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class CreditLimit extends Model
{
    use HasFactory, Auditable;

    protected $table = 'credit_limits';

    protected $fillable = [
        'name',
        'description',
        'rules',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
        ];
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class);
    }
}
