<?php

namespace App\Mail;

use App\Models\EventInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EventInvitation $invitation,
        public string $responseUrl,
        public string $fromAddress,
        public string $fromName,
        public ?string $replyToAddress = null,
    ) {
    }

    public function build()
    {
        $this->invitation->loadMissing(['event.tenant', 'member']);

        $mail = $this->subject('Einladung: ' . $this->invitation->event->title)
            ->from($this->fromAddress, $this->fromName)
            ->view('mail.layout', [
                'body' => $this->bodyHtml(),
                'tenant' => $this->invitation->event->tenant,
            ]);

        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress, $this->invitation->event->tenant->name ?: $this->fromName);
        }

        return $mail;
    }

    private function bodyHtml(): string
    {
        $event = $this->invitation->event;
        $member = $this->invitation->member;
        $tenantName = e($event->tenant?->name ?: 'Clubano');
        $memberName = e($member->first_name ?: $member->full_name);
        $eventTitle = e($event->title);
        $date = e($event->start->format('d.m.Y H:i') . ' Uhr');
        $location = e($event->location ?: 'Ort folgt');
        $url = e($this->responseUrl);

        return <<<HTML
<p>Hallo {$memberName},</p>
<p>du bist zu folgender Aktivität eingeladen:</p>
<p>
    <strong>{$eventTitle}</strong><br>
    {$date}<br>
    {$location}
</p>
<p>Bitte gib kurz Rückmeldung, ob du teilnehmen kannst.</p>
<p>
    <a href="{$url}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">
        Zu- oder Absage öffnen
    </a>
</p>
<p style="font-size:13px;color:#6b7280;">Falls der Button nicht funktioniert, öffne diesen Link:<br>{$url}</p>
<p>Viele Grüße<br>{$tenantName}</p>
HTML;
    }
}
