<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DisbursementStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pencairan Dana Berhasil - ' . ($this->data['batchId'] ?? 'NemuHUB')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.finance.disbursement-status',
            with: $this->data
        );
    }
}
