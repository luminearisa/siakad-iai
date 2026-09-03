<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class CurriculumYear extends Model
{
    use HasFactory, Auditable;

    protected $table = 'curriculum_years';

    protected $fillable = [
        'year',
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class);
    }
}
