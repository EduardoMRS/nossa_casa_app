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
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
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
        $subject = __('mail.welcome.subject', ['brand' => $branding['name']]);

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
                'preheader' => __('mail.welcome.preheader', ['brand' => $branding['name']]),
                'heading' => __('mail.welcome.heading', ['name' => $this->user->first_name]),
                'introduction' => __('mail.welcome.introduction', ['brand' => $branding['name']]),
                'lines' => __('mail.welcome.lines'),
                'actionText' => __('mail.welcome.action', ['brand' => $branding['name']]),
                'actionUrl' => $branding['portal_url'],
                'outro' => __('mail.welcome.outro'),
            ],
        );
    }

    /**
     * Configure a church-owned SMTP transport only for this message.
     */
    public function send($mailer): mixed
    {
        $churchMailManager = app(ChurchMailManager::class);

        if ($this->church !== null) {
            $churchMailManager->configureFor($this->church);
        } else {
            $churchMailManager->reset();
        }

        try {
            return parent::send($mailer);
        } finally {
            $churchMailManager->reset();
        }
    }
}
