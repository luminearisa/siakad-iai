<?php

namespace Modules\Integrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only access log for integration endpoints.
 *
 * Rows are written on the way out of every request (including rejected ones), which
 * makes the log the primary audit trail for external data pulls — required because
 * the student endpoints can carry personal data.
 */
class ApiRequestLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'api_client_id',
        'api_key_id',
        'method',
        'path',
        'query',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function key(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    /**
     * Whether the request completed successfully.
     */
    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }
}
