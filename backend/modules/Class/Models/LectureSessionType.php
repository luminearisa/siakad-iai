<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Traits\Auditable;

class LectureSessionType extends Model
{
    use HasFactory, Auditable;

    protected $table = 'lecture_session_types';

    protected $fillable = [
        'name',
        'short_name',
        'category',
        'credit_type',
        'counts_attendance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'counts_attendance' => 'boolean',
        ];
    }
}
