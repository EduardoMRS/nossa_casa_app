<?php

namespace App\Notifications;

use App\Models\Church;
use App\Support\ChurchMailManager;
use App\Support\MailBrandingResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

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

    public function toMail(object $notifiable): MailMessage
    {
        /** @var Church|null $church */
        $church = $notifiable->church;
        $branding = app(MailBrandingResolver::class)->resolve($church);
        $message = (new MailMessage)
            ->subject(__('mail.reset_password.subject', ['brand' => $branding['name']]))
            ->view([
                'html' => 'mail.branded',
                'text' => 'mail.branded-text',
            ], [
                'branding' => $branding,
                'preheader' => __('mail.reset_password.preheader'),
                'heading' => __('mail.reset_password.heading'),
                'introduction' => __('mail.reset_password.introduction'),
                'lines' => [__('mail.reset_password.expiration', [
                    'count' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ])],
                'actionText' => __('mail.reset_password.action'),
                'actionUrl' => $this->resetUrl($notifiable),
                'outro' => __('mail.reset_password.outro'),
            ]);

        if ($church === null) {
            return $message;
        }

        $churchMailManager = app(ChurchMailManager::class);
        $mailSetting = $churchMailManager->configureFor($church);

        if ($mailSetting === null) {
            return $message;
        }

        return $message
            ->mailer('church')
            ->from(
                $mailSetting->from_address,
                $mailSetting->from_name ?: $branding['name'],
            )
            ->withSymfonyMessage(function (Email $email) use ($churchMailManager): void {
                $churchMailManager->reset();
            });
    }
}
