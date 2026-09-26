<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;

class MbkmSelectionScore extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_selection_scores';

    protected $fillable = [
        'application_id',
        'criteria_id',
        'reviewer_id',
        'score',
        'notes',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'scored_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MbkmApplication::class, 'application_id');
    }

    public function criteria(): BelongsTo
    {
        return $this->belongsTo(MbkmSelectionCriteria::class, 'criteria_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
