<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

/**
 * Reference/master data for MBKM program types (Pertukaran Mahasiswa, Magang,
 * Studi Independen, ...). Kept as data, never as hardcoded conditions.
 */
class MbkmProgramType extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_program_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(MbkmProgram::class, 'program_type_id');
    }
}
