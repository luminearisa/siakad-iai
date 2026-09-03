<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Enums\ClassLecturerRole;
use Modules\Lecturer\Models\Lecturer;

class ClassLecturer extends Model
{
    use HasFactory, Auditable;

    protected $table = 'class_lecturers';

    protected $fillable = [
        'class_id',
        'lecturer_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => ClassLecturerRole::class,
        ];
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
