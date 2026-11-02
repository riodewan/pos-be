<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformApprovalNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {}

    public function envelope(): Envelope
    {
        $status = ($this->data['status'] ?? 'APPROVED') === 'APPROVED' ? 'Disetujui' : 'Ditolak';
        return new Envelope(
            subject: 'Status Pengajuan ' . ($this->data['title'] ?? 'Layanan') . ': ' . $status . ' - NemuHUB'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.platform.approval-notice',
            with: $this->data
        );
    }
}

