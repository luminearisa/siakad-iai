<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class StudentEducation extends Model
{
    use HasFactory, Auditable;

    protected $table = 'student_educations';

    protected $fillable = [
        'student_id',
        'institution_name',
        'level',
        'major',
        'graduation_year',
        'certificate_number',
        'notes',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
