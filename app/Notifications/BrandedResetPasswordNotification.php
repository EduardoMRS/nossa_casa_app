<?php

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use App\Models\Church;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class BrandedResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(string $token)
    {
        parent::__construct($token);

        $this->afterCommit();
        $this->onQueue('mail');
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => 'mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): ResetPasswordMail
    {
        /** @var Church|null $church */
        $church = $notifiable->church;
        $mail = new ResetPasswordMail(
            user: $notifiable,
            resetUrl: $this->resetUrl($notifiable),
            church: $church,
        );

        return $mail->to($notifiable);
    }
}
