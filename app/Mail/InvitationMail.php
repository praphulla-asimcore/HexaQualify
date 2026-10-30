<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Mail\Mailables\Content;

class InvitationMail extends HexaMail
{
    public function __construct(public Invitation $invitation, public string $link) {}

    protected function subjectLine(): string
    {
        return 'Hexamatics pre-pitch screening: '.$this->invitation->version->questionnaire->name;
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation', with: [
            'name' => $this->invitation->contact_name,
            'company' => $this->invitation->vendor_company,
            'questionnaire' => $this->invitation->version->questionnaire->name,
            'deadline' => $this->invitation->expires_at->timezone('Asia/Kathmandu')->format('j M Y'),
            'link' => $this->link,
        ]);
    }
}
