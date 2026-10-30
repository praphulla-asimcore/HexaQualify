<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;

class ReminderMail extends HexaMail implements ShouldQueue
{
    public function __construct(public Invitation $invitation) {}

    protected function subjectLine(): string
    {
        return 'Reminder: your Hexamatics screening questionnaire is due soon';
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reminder', with: [
            'name' => $this->invitation->contact_name,
            'questionnaire' => $this->invitation->version->questionnaire->name,
            'deadline' => $this->invitation->expires_at->timezone('Asia/Kathmandu')->format('j M Y'),
        ]);
    }
}
