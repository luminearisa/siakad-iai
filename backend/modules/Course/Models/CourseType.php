<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class CourseType extends Model
{
    use HasFactory, Auditable;

    protected $table = 'course_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
    ];

    /**
     * Get courses of this type.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
