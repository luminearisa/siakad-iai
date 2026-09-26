<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\DocumentStatus;

/**
 * Single document store for the whole MBKM module (requirement documents,
 * cooperation deeds, learning agreement attachments, final reports, completion
 * certificates). Deliberately the only file storage the module owns.
 */
class MbkmDocument extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_documents';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'category',
        'title',
        'original_name',
        'file_path',
        'mime_type',
        'size',
        'status',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'verified_at' => 'datetime',
            'expires_at' => 'date',
            'size' => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
