<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DueSoonMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Loan $loan) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Reminder: borrowed equipment due soon'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.due-soon',
        );
    }
}
