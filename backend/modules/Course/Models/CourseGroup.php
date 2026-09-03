<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;

class CourseGroup extends Model
{
    use HasFactory, Auditable;

    protected $table = 'course_groups';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
    ];

    /**
     * Get courses of this group.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
