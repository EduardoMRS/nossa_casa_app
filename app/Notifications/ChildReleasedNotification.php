<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChildReleasedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $childName,
        public readonly string $classroomName,
        public readonly string $pickupName,
        public readonly ?string $pickupPhone,
        public readonly string $checkedOutAt,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'child_released',
            'child_name' => $this->childName,
            'classroom_name' => $this->classroomName,
            'pickup_name' => $this->pickupName,
            'pickup_phone' => $this->pickupPhone,
            'checked_out_at' => $this->checkedOutAt,
        ];
    }
}
