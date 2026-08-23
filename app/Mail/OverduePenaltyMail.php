<?php

namespace App\Mail;

use App\Models\Loan;
use App\Models\Penalty;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OverduePenaltyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Loan $loan, public Penalty $penalty) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Notice: overdue loan and borrowing penalty imposed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.overdue-penalty',
        );
    }
}
