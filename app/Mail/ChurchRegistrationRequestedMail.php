<?php

namespace App\Mail;

use App\Models\Church;
use App\Models\ChurchRegistrationRequest;
use App\Support\ChurchMailManager;
use App\Support\MailBrandingResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChurchRegistrationRequestedMail extends BrandedMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ChurchRegistrationRequest $registrationRequest)
    {
        $this->afterCommit();
        $this->onQueue('mail');
    }

    public function envelope(): Envelope
    {
        $church = $this->churchForMail();
        $branding = app(MailBrandingResolver::class)->resolve($church);
        $mailSetting = $church
            ? app(ChurchMailManager::class)->resolveFor($church)
            : null;
        $subject = __('mail.church_registration_request.subject', [
            'church' => $this->registrationRequest->name,
        ]);

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
        $this->registrationRequest->loadMissing(['community', 'requestedParentChurch', 'requester']);
        $church = $this->churchForMail();
        $branding = app(MailBrandingResolver::class)->resolve($church);
        $lines = [
            __('mail.church_registration_request.community', [
                'community' => $this->registrationRequest->community->name,
            ]),
        ];

        if ($this->registrationRequest->requestedParentChurch) {
            $lines[] = __('mail.church_registration_request.parent_church', [
                'church' => $this->registrationRequest->requestedParentChurch->name,
            ]);
        }

        if ($this->registrationRequest->contact_email) {
            $lines[] = __('mail.church_registration_request.contact', [
                'email' => $this->registrationRequest->contact_email,
            ]);
        }

        if ($this->registrationRequest->address) {
            $lines[] = __('mail.church_registration_request.address', [
                'address' => $this->registrationRequest->address,
            ]);
        }

        $lines[] = __('mail.church_registration_request.language', [
            'language' => __('mail.church_registration_request.languages.'.($this->registrationRequest->locale ?: 'pt')),
        ]);

        return new Content(
            view: 'mail.branded',
            text: 'mail.branded-text',
            with: [
                'branding' => $branding,
                'preheader' => __('mail.church_registration_request.preheader'),
                'heading' => __('mail.church_registration_request.heading'),
                'introduction' => __('mail.church_registration_request.introduction', [
                    'requester' => $this->registrationRequest->requester->name,
                    'church' => $this->registrationRequest->name,
                ]),
                'lines' => $lines,
                'actionText' => __('mail.church_registration_request.action'),
                'actionUrl' => rtrim((string) config('app.url'), '/'),
                'outro' => __('mail.church_registration_request.outro'),
            ],
        );
    }

    protected function churchForMail(): ?Church
    {
        return $this->registrationRequest->requestedParentChurch;
    }
}
