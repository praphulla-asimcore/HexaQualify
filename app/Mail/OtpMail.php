<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Mail\Mailables\Content;

class OtpMail extends HexaMail
{
    public function __construct(public Invitation $invitation, public string $code) {}

    protected function subjectLine(): string
    {
        return 'Your HexaQualify verification code: '.$this->code;
    }

    public function content(): Content
    {
        return new Content(view: 'mail.otp', with: [
            'code' => $this->code,
            'minutes' => config('hexaqualify.otp.ttl_minutes'),
        ]);
    }
}
