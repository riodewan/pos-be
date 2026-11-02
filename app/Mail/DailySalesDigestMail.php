<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySalesDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Laporan Penjualan Harian - ' . ($this->data['outletName'] ?? 'NemuHUB')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reports.daily-sales-digest',
            with: $this->data
        );
    }
}

