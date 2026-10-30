<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;

/** Optional result notice to the vendor. Never discloses scores. */
class ResultMail extends HexaMail implements ShouldQueue
{
    public function __construct(public Invitation $invitation, public bool $qualified) {}

    protected function subjectLine(): string
    {
        return 'Your Hexamatics pre-pitch screening';
    }

    public function content(): Content
    {
        return new Content(view: 'mail.result', with: [
            'name' => $this->invitation->contact_name,
            'company' => $this->invitation->vendor_company,
            'qualified' => $this->qualified,
        ]);
    }
}
