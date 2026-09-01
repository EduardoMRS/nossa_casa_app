<?php

namespace App\Mail;

use App\Models\Church;
use App\Support\ChurchMailManager;
use Illuminate\Mail\Mailable;

abstract class BrandedMailable extends Mailable
{
    /**
     * Configure a church-owned SMTP transport only for the current message.
     */
    public function send($mailer): mixed
    {
        $churchMailManager = app(ChurchMailManager::class);
        $church = $this->churchForMail();

        if ($church !== null) {
            $churchMailManager->configureFor($church);
        } else {
            $churchMailManager->reset();
        }

        try {
            return parent::send($mailer);
        } finally {
            $churchMailManager->reset();
        }
    }

    abstract protected function churchForMail(): ?Church;
}
