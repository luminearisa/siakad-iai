<?php

namespace Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;

class GradeRevision extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'student_grade_id',
        'old_score',
        'new_score',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_score' => 'decimal:2',
            'new_score' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * The student grade that was revised.
     */
    public function studentGrade(): BelongsTo
    {
        return $this->belongsTo(StudentGrade::class, 'student_grade_id');
    }

    /**
     * The user who performed this revision.
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
