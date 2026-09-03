<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Traits\Auditable;

class GradingComponent extends Model
{
    use HasFactory, Auditable;

    protected $table = 'grading_components';

    protected $fillable = [
        'name',
        'short_name',
        'evaluation_method',
        'component_group',
        'default_weight',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'default_weight' => 'float',
        ];
    }
}
