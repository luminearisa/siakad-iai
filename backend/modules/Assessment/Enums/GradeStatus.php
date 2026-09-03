<?php

namespace Modules\Assessment\Enums;

enum GradeStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case FINAL = 'final';
    case REVISION_REQUIRED = 'revision_required';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan (Submitted)',
            self::FINAL => 'Final (Terkunci)',
            self::REVISION_REQUIRED => 'Perlu Revisi',
        };
    }
}
