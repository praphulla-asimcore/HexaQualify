<?php

namespace App\Mail;

use App\Models\VendorResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;

/** To the inviting reviewer (and distribution list) when a vendor submits. */
class SubmittedMail extends HexaMail implements ShouldQueue
{
    public function __construct(public VendorResponse $response) {}

    protected function subjectLine(): string
    {
        return 'Vendor submitted: '.$this->response->invitation->vendor_company;
    }

    public function content(): Content
    {
        $inv = $this->response->invitation;

        return new Content(view: 'mail.submitted', with: [
            'company' => $inv->vendor_company,
            'questionnaire' => $inv->version->questionnaire->name,
            'link' => route('responses.show', $this->response),
        ]);
    }
}
