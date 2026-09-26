<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\CompletionStatus;

class MbkmCompletion extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_completions';

    protected $fillable = [
        'participant_id',
        'status',
        'requirements_snapshot',
        'unmet_requirements',
        'checked_at',
        'checked_by',
        'verified_at',
        'verified_by',
        'completed_at',
        'notes',
        'certificate_document_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompletionStatus::class,
            'requirements_snapshot' => 'array',
            'unmet_requirements' => 'array',
            'checked_at' => 'datetime',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function certificateDocument(): BelongsTo
    {
        return $this->belongsTo(MbkmDocument::class, 'certificate_document_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
