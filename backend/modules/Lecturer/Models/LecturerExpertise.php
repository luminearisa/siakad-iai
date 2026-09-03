<?php

namespace Modules\Lecturer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;

class LecturerExpertise extends Model
{
    use HasFactory, Auditable;

    protected $table = 'lecturer_expertises';

    protected $fillable = [
        'lecturer_id',
        'name',
        'description',
    ];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
