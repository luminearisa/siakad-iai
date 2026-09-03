<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Traits\Auditable;

class LectureProgram extends Model
{
    use HasFactory, Auditable;

    protected $table = 'lecture_programs';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];
}
