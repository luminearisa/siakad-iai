<?php

namespace Modules\Graduation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YudisiumDocumentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'yudisium_participant_id',
        'requirement_id',
        'file_path',
        'status',
        'notes',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(YudisiumParticipant::class, 'yudisium_participant_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(YudisiumRequirement::class, 'requirement_id');
    }
}
