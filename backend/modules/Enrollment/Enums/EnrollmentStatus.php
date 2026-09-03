<?php

namespace Modules\Enrollment\Enums;

enum EnrollmentStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REVISION_REQUIRED = 'revision_required';
    case CANCELLED = 'cancelled';
    case LOCKED = 'locked';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
