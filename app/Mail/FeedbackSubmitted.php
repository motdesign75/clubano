<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public Feedback $feedback;

    /**
     * Create a new message instance.
     */
    public function __construct(Feedback $feedback)
    {
        $this->feedback = $feedback;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📬 Neues Feedback auf Clubano',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.feedback',
            with: [
                'feedback' => $this->feedback,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (blank($this->feedback->screenshot_path)) {
            return [];
        }

        $filename = 'feedback-screenshot.' . pathinfo($this->feedback->screenshot_path, PATHINFO_EXTENSION);

        return [
            Attachment::fromStorageDisk('public', $this->feedback->screenshot_path)
                ->as($filename),
        ];
    }
}
