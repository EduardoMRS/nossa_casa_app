<?php

namespace App\Mail;

use App\Models\Church;
use App\Models\User;
use App\Support\ChurchMailManager;
use App\Support\MailBrandingResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends BrandedMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        public ?Church $church = null,
    ) {
        $this->afterCommit();
        $this->onQueue('mail');
    }

    public function envelope(): Envelope
    {
        $branding = app(MailBrandingResolver::class)->resolve($this->church);
        $mailSetting = $this->church
            ? app(ChurchMailManager::class)->resolveFor($this->church)
            : null;
        $subject = __('mail.reset_password.subject', ['brand' => $branding['name']]);

        if ($mailSetting?->from_address) {
            return new Envelope(
                from: new Address(
                    $mailSetting->from_address,
                    $mailSetting->from_name ?: $branding['name'],
                ),
                subject: $subject,
            );
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $branding = app(MailBrandingResolver::class)->resolve($this->church);

        return new Content(
            view: 'mail.branded',
            text: 'mail.branded-text',
            with: [
                'branding' => $branding,
                'preheader' => __('mail.reset_password.preheader'),
                'heading' => __('mail.reset_password.heading'),
                'introduction' => __('mail.reset_password.introduction'),
                'lines' => [__('mail.reset_password.expiration', [
                    'count' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ])],
                'actionText' => __('mail.reset_password.action'),
                'actionUrl' => $this->resetUrl,
                'outro' => __('mail.reset_password.outro'),
            ],
        );
    }

    protected function churchForMail(): ?Church
    {
        return $this->church;
    }
}
