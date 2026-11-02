<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Peringatan Stok Bahan Baku Menipis - ' . ($this->data['outletName'] ?? 'NemuHUB')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inventory.low-stock-alert',
            with: $this->data
        );
    }
}

