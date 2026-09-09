<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AutomatedClubMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $mailSubject,
        public string $bodyHtml,
        public string $fromAddress,
        public string $fromName,
        public ?string $replyToAddress = null,
    ) {
    }

    public function build()
    {
        $mail = $this->subject($this->mailSubject)
            ->from($this->fromAddress, $this->fromName)
            ->view('mail.layout', [
                'body' => $this->bodyHtml,
                'tenant' => $this->tenant,
            ]);

        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress, $this->tenant->name ?: $this->fromName);
        }

        return $mail;
    }
}
