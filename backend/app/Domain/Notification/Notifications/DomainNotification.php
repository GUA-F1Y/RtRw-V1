<?php

declare(strict_types=1);

namespace App\Domain\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class DomainNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue((string) config('rtrw.queues.notifications', 'notifications'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return config('rtrw.notifications.channels', ['database']);
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;
}
