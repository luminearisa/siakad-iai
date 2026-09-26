<?php

namespace Modules\MBKM\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database notification used across the MBKM module.
 *
 * Deliberately channel-agnostic beyond `database` so the module does not invent
 * a second delivery mechanism: the payload is rendered by the client from the
 * `data` column.
 */
class MbkmDatabaseNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $event,
        public string $title,
        public string $message,
        public array $payload = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'module' => 'mbkm',
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'payload' => $this->payload,
        ];
    }
}
