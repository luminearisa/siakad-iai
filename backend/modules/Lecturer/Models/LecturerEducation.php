<?php

namespace Modules\Lecturer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class LecturerEducation extends Model
{
    use HasFactory, Auditable;

    protected $table = 'lecturer_educations';

    protected $fillable = [
        'lecturer_id',
        'degree',
        'institution_name',
        'major',
        'graduation_year',
    ];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
