<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Base for branded HexaQualify emails (§10.5). */
abstract class HexaMail extends Mailable
{
    use Queueable, SerializesModels;

    abstract protected function subjectLine(): string;

    public function envelope(): Envelope
    {
        $replyTo = Setting::get('reply_to');

        return new Envelope(
            from: new Address(config('mail.from.address'), Setting::get('sender_name') ?: config('mail.from.name')),
            replyTo: $replyTo ? [new Address($replyTo)] : [],
            subject: $this->subjectLine(),
        );
    }
}
