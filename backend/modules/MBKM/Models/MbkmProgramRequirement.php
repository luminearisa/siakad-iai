<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\MBKM\Enums\RequirementType;

class MbkmProgramRequirement extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_program_requirements';

    protected $fillable = [
        'program_id',
        'type',
        'code',
        'name',
        'description',
        'is_mandatory',
        'is_document',
        'rule',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => RequirementType::class,
            'is_mandatory' => 'boolean',
            'is_document' => 'boolean',
            'rule' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MbkmProgram::class, 'program_id');
    }
}
