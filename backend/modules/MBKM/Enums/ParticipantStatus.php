<?php

namespace Modules\MBKM\Enums;

enum ParticipantStatus: string
{
    case ASSIGNED = 'assigned';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case WITHDRAWN = 'withdrawn';
    case TERMINATED = 'terminated';
    case FAILED = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses that occupy a program quota slot.
     */
    public static function occupyingQuota(): array
    {
        return [
            self::ASSIGNED->value,
            self::ONGOING->value,
            self::COMPLETED->value,
        ];
    }

    public static function isActive(self $status): bool
    {
        return in_array($status->value, [self::ASSIGNED->value, self::ONGOING->value], true);
    }

    /**
     * Allowed forward transitions of the participant lifecycle.
     *
     * Every terminal status maps to an empty list: a participant that has
     * completed, withdrawn, been terminated or failed may not be silently
     * resurrected into an active state.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            self::ASSIGNED->value => [self::ONGOING->value, self::WITHDRAWN->value, self::TERMINATED->value],
            self::ONGOING->value => [self::COMPLETED->value, self::WITHDRAWN->value, self::TERMINATED->value, self::FAILED->value],
            self::COMPLETED->value => [],
            self::WITHDRAWN->value => [],
            self::TERMINATED->value => [],
            self::FAILED->value => [],
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target->value, self::transitions()[$this->value] ?? [], true);
    }

    public static function isTerminal(self $status): bool
    {
        return (self::transitions()[$status->value] ?? []) === [];
    }

    /**
     * Statuses that may only ever be reached through their dedicated workflow
     * endpoint, never through a generic administrative update.
     *
     * - `ongoing`   -> `MbkmParticipantService::start()` (notifies the student)
     * - `completed` -> `MbkmCompletionService::verify()` (requirements check,
     *                  completion record, notification)
     * - `withdrawn` / `terminated` -> an approved `MbkmWithdrawalRequest`
     *                  decision
     *
     * Reaching them by writing the column directly would skip those side
     * effects, would leave no completion/withdrawal record, and — because the
     * generic update bypassed the history service — would leave no trace in
     * `mbkm_status_histories` either. `assigned` is likewise only ever set by
     * `assignFromApplication()`.
     *
     * The only status an administrative update may set is `failed`, which has
     * no dedicated endpoint.
     *
     * @return array<int, string>
     */
    public static function workflowOnly(): array
    {
        return [
            self::ONGOING->value,
            self::COMPLETED->value,
            self::WITHDRAWN->value,
            self::TERMINATED->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNED => 'Ditetapkan',
            self::ONGOING => 'Sedang Berjalan',
            self::COMPLETED => 'Selesai',
            self::WITHDRAWN => 'Mengundurkan Diri',
            self::TERMINATED => 'Dihentikan',
            self::FAILED => 'Tidak Lulus',
        };
    }
}
