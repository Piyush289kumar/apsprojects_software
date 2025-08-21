<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Filament\Notifications\Notification as FilamentNotification;

class UserNotification extends Notification
{
    use Queueable;

    public string $title;
    public string $type; // success, danger, warning, info
    public ?string $message;

    /**
     * Constructor
     *
     * @param string $title   Title for Filament toast & database
     * @param string|null $message Optional message body
     * @param string $type    success | danger | warning | info
     */
    public function __construct(string $title, ?string $message = null, string $type = 'success')
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Determine which channels to send the notification on.
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Database representation of notification
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
        ];
    }

    /**
     * Send a Filament toast notification instantly (optional)
     */
    public function sendFilamentToast(): void
    {
        FilamentNotification::make()
                    ->title($this->title)
                    ->body($this->message)
            ->{$this->type}() // calls success(), danger(), warning(), info()
                ->send();
    }
}
