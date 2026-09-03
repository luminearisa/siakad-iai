<?php

namespace Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Course\Models\Course;

class KrsPackageItem extends Model
{
    use HasFactory;

    protected $table = 'krs_package_items';

    protected $fillable = [
        'krs_package_id',
        'course_id',
        'credits',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
        ];
    }

    public function krsPackage(): BelongsTo
    {
        return $this->belongsTo(KrsPackage::class, 'krs_package_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
