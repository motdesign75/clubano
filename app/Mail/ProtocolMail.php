<?php

namespace App\Mail;

use App\Models\Protocol;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ProtocolMail extends Mailable
{
    use Queueable, SerializesModels;

    public $protocol;

    public function __construct(Protocol $protocol)
    {
        $this->protocol = $protocol;
    }

    private function protocolAttachments(): array
    {
        $attachments = $this->protocol->attachments ?? $this->protocol->attachment_paths ?? [];

        if (is_string($attachments)) {
            return [$attachments];
        }

        return is_array($attachments) ? $attachments : [];
    }

    private function attachmentDiskForPath(string $path): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public function build()
    {
        $pdf = Pdf::loadView('pdf.protocol', [
            'protocol' => $this->protocol
        ]);

        $fileName = 'Protokoll_' . str_replace(' ', '_', $this->protocol->title) . '.pdf';

        $mail = $this->subject('Protokoll: ' . $this->protocol->title)
            ->view('emails.protocol')
            ->attachData(
                $pdf->output(),
                $fileName,
                ['mime' => 'application/pdf']
            );

        foreach ($this->protocolAttachments() as $file) {
            if (! is_string($file) || $file === '') {
                continue;
            }

            $disk = $this->attachmentDiskForPath($file);

            if ($disk) {
                $mail->attachFromStorageDisk($disk, $file, basename($file));
            }
        }

        return $mail;
    }
}
