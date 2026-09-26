<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Audit\Traits\Auditable;
use Modules\Lecturer\Models\Lecturer;
use Modules\MBKM\Enums\IssueSeverity;
use Modules\MBKM\Enums\IssueStatus;

class MbkmIssue extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_issues';

    protected $fillable = [
        'participant_id',
        'category',
        'severity',
        'title',
        'description',
        'status',
        'reported_by',
        'assigned_to',
        'resolution',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'assigned_to');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(MbkmDocument::class, 'documentable');
    }
}
