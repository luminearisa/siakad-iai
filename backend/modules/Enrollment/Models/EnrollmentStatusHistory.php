<?php

namespace Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Models\User;

/**
 * Workflow audit trail for KRS (batal-tambah included).
 *
 * Records who moved an enrollment — or one of its items — from which status to
 * which status, and why. This is what makes a class dropped from an approved KRS
 * traceable afterwards instead of vanishing with a hard delete.
 */
class EnrollmentStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'enrollment_status_histories';

    protected $fillable = [
        'enrollment_id',
        'enrollment_item_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollmentItem::class, 'enrollment_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Chronological trail of one enrollment.
     */
    public function scopeForEnrollment(Builder $query, StudentEnrollment|int $enrollment): Builder
    {
        return $query->where('enrollment_id', $enrollment instanceof StudentEnrollment ? $enrollment->id : $enrollment)
            ->orderBy('id');
    }

    /**
     * Append one trail entry.
     */
    public static function record(
        StudentEnrollment $enrollment,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $notes = null,
        ?StudentEnrollmentItem $item = null,
        ?array $meta = null,
        ?int $actorId = null,
    ): self {
        return static::create([
            'enrollment_id' => $enrollment->id,
            'enrollment_item_id' => $item?->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actorId ?? Auth::id(),
            'notes' => $notes,
            'meta' => $meta,
        ]);
    }
}
