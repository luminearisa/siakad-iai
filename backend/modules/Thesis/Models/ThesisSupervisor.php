<?php

namespace Modules\Thesis\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Lecturer\Models\Lecturer;

class ThesisSupervisor extends Model
{
    use HasFactory;

    protected $table = 'thesis_supervisors';

    protected $fillable = [
        'thesis_id',
        'lecturer_id',
        'order',
        'role',
        'status',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class, 'thesis_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_id');
    }
}
