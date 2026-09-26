<?php

namespace Modules\MBKM\Enums;

/**
 * Lifecycle status of an MBKM program.
 *
 * Mirrors the lifecycle wording used elsewhere in the repository
 * (draft -> published -> ... -> archived) so the transition rules stay familiar.
 */
enum ProgramStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case REGISTRATION_CLOSED = 'registration_closed';
    case SELECTION = 'selection';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses in which the program is visible to students.
     */
    public static function publiclyVisible(): array
    {
        return [
            self::PUBLISHED->value,
            self::REGISTRATION_CLOSED->value,
            self::SELECTION->value,
            self::ONGOING->value,
            self::COMPLETED->value,
        ];
    }

    /**
     * Allowed forward transitions. A status may always be moved to CANCELLED/ARCHIVED
     * by an administrator unless the program already reached a terminal state.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            self::DRAFT->value => [self::PUBLISHED->value, self::CANCELLED->value, self::ARCHIVED->value],
            self::PUBLISHED->value => [self::REGISTRATION_CLOSED->value, self::SELECTION->value, self::CANCELLED->value, self::ARCHIVED->value],
            self::REGISTRATION_CLOSED->value => [self::SELECTION->value, self::CANCELLED->value, self::ARCHIVED->value],
            self::SELECTION->value => [self::ONGOING->value, self::CANCELLED->value, self::ARCHIVED->value],
            self::ONGOING->value => [self::COMPLETED->value, self::CANCELLED->value],
            self::COMPLETED->value => [self::ARCHIVED->value],
            self::CANCELLED->value => [self::ARCHIVED->value],
            self::ARCHIVED->value => [],
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target->value, self::transitions()[$this->value] ?? [], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Dibuka (Pendaftaran)',
            self::REGISTRATION_CLOSED => 'Pendaftaran Ditutup',
            self::SELECTION => 'Seleksi',
            self::ONGOING => 'Berjalan',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
            self::ARCHIVED => 'Diarsipkan',
        };
    }
}
