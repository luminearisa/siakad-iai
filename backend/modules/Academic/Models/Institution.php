<?php

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Audit\Traits\Auditable;

class Institution extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'short_name',
        'code',
        'address',
        'phone',
        'website',
        'logo_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AcademicStatus::class,
        ];
    }

    /**
     * Get the faculties for the institution.
     */
    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }
}
