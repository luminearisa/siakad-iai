<?php

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Audit\Traits\Auditable;

class AcademicYear extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * Tahun ajaran baru selalu nonaktif secara default. Aktivasi adalah tindakan
     * eksplisit (endpoint set-active) supaya invariant "hanya satu periode aktif"
     * tidak pernah dilanggar oleh create/update biasa.
     */
    protected $attributes = [
        'status' => 'inactive',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AcademicStatus::class,
        ];
    }

    /**
     * Get the semesters for the academic year.
     */
    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }
}
