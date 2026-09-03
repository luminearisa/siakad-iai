<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Traits\Auditable;

class Setting extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Get casted typed value.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer', 'int' => (int) $this->value,
            'boolean', 'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($this->value ?? '[]', true),
            'float', 'double' => (float) $this->value,
            default => $this->value,
        };
    }

    /**
     * Set value with type handling.
     */
    public function setTypedValue(mixed $val): void
    {
        if (is_array($val) || is_object($val)) {
            $this->value = json_encode($val);
            $this->type = 'json';
        } elseif (is_bool($val)) {
            $this->value = $val ? '1' : '0';
            $this->type = 'boolean';
        } elseif (is_int($val)) {
            $this->value = (string) $val;
            $this->type = 'integer';
        } elseif (is_float($val)) {
            $this->value = (string) $val;
            $this->type = 'float';
        } else {
            $this->value = (string) $val;
            $this->type = $this->type ?: 'string';
        }
    }
}
