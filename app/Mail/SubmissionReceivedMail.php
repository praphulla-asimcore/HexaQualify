<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;

/** Confirmation to the vendor after submission. No score or result. */
class SubmissionReceivedMail extends HexaMail implements ShouldQueue
{
    public function __construct(public Invitation $invitation) {}

    protected function subjectLine(): string
    {
        return 'We received your Hexamatics screening questionnaire';
    }

    public function content(): Content
    {
        return new Content(view: 'mail.received', with: [
            'name' => $this->invitation->contact_name,
            'questionnaire' => $this->invitation->version->questionnaire->name,
        ]);
    }
}
