<?php

namespace Modules\Student\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class StudentFamily extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'student_id',
        'relationship',
        'full_name',
        'phone',
        'occupation',
        'address',
        'notes',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
