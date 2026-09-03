<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class GradeScale extends Model
{
    use HasFactory, Auditable;

    protected $table = 'grade_scales';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GradeScaleItem::class)->orderBy('min_score', 'desc');
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class);
    }
}
