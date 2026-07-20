<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Member;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Services\MailTrackingService;
use App\Services\TemplateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class MailController extends Controller
{
    public function __construct(
        private readonly MailTrackingService $mailTrackingService,
    ) {
    }

    public function create()
    {
        $tenantId = auth()->user()->tenant_id;
        $preselectedMemberIds = collect(explode(',', (string) request('members')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $preselectedContactIds = collect(explode(',', (string) request('contacts')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $templates = Template::where('tenant_id', $tenantId)
            ->whereIn('type', [Template::TYPE_MAIL, Template::TYPE_MAIL_AND_LETTER])
            ->orderBy('name')
            ->get();

        $members = Member::where('tenant_id', $tenantId)
            ->whereNull('exit_date')
            ->orderBy('last_name')
            ->get();
        $contacts = Contact::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->orderBy('last_name')
            ->orderBy('organization')
            ->get();

        $selectedTemplateId = request('template')
            ? (int) request('template')
            : optional($templates->first())->id;

        $preselectedDirectEmails = trim((string) request('emails', ''));

        return view('mail.create', compact(
            'templates',
            'members',
            'contacts',
            'preselectedMemberIds',
            'preselectedContactIds',
            'selectedTemplateId',
            'preselectedDirectEmails'
        ));
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'template_id' => [
                'required',
                Rule::exists('templates', 'id')->where(function ($query) {
                    $query->where('tenant_id', auth()->user()->tenant_id)
                        ->whereIn('type', [Template::TYPE_MAIL, Template::TYPE_MAIL_AND_LETTER]);
                }),
            ],
            'members' => 'nullable|array',
            'members.*' => [
                'integer',
                Rule::exists('members', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
            'contacts' => 'nullable|array',
            'contacts.*' => [
                'integer',
                Rule::exists('contacts', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
            'direct_emails' => 'nullable|string',
        ]);

        $directEmailParts = $this->splitDirectEmails($validated['direct_emails'] ?? null);
        $invalidDirectEmails = collect($directEmailParts)
            ->filter(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if ($invalidDirectEmails !== []) {
            return back()
                ->withErrors(['direct_emails' => 'Diese E-Mail-Adressen sind ungueltig: ' . implode(', ', $invalidDirectEmails)])
                ->withInput();
        }

        $directEmails = collect($directEmailParts)
            ->unique()
            ->values()
            ->all();

        if (empty($validated['members'] ?? []) && empty($validated['contacts'] ?? []) && empty($directEmails)) {
            return back()
                ->withErrors(['members' => 'Bitte waehle mindestens ein Mitglied, einen Kontakt oder gib mindestens eine freie E-Mail-Adresse ein.'])
                ->withInput();
        }

        $tenant = auth()->user()->tenant;

        $template = Template::where('tenant_id', $tenant->id)
            ->whereIn('type', [Template::TYPE_MAIL, Template::TYPE_MAIL_AND_LETTER])
            ->findOrFail($validated['template_id']);

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = $tenant->email && $tenant->email !== $fromAddress
            ? $tenant->email
            : null;

        $sentCount = 0;
        $skippedCount = 0;

        foreach (($validated['members'] ?? []) as $memberId) {
            $member = Member::where('tenant_id', $tenant->id)->find($memberId);

            if (! $member || ! $member->email) {
                $skippedCount++;
                continue;
            }

            $this->sendTrackedTemplateMail(
                recipient: $member,
                template: $template,
                tenant: $tenant,
                toEmail: $member->email,
                recipientType: 'member',
                memberId: $member->id,
                contactId: null,
                recipientName: $member->full_name,
                recipientReference: $member->email,
                fromAddress: $fromAddress,
                fromName: $fromName,
                replyToAddress: $replyToAddress,
            );

            $sentCount++;
        }

        foreach (($validated['contacts'] ?? []) as $contactId) {
            $contact = Contact::where('tenant_id', $tenant->id)->find($contactId);

            if (! $contact || ! $contact->primary_email) {
                $skippedCount++;
                continue;
            }

            $this->sendTrackedTemplateMail(
                recipient: $contact,
                template: $template,
                tenant: $tenant,
                toEmail: $contact->primary_email,
                recipientType: 'contact',
                memberId: null,
                contactId: $contact->id,
                recipientName: $contact->display_name,
                recipientReference: $contact->primary_email,
                fromAddress: $fromAddress,
                fromName: $fromName,
                replyToAddress: $replyToAddress,
            );

            $sentCount++;
        }

        foreach ($directEmails as $email) {
            $recipient = [
                'tenant_id' => $tenant->id,
                'email' => $email,
                'name' => '',
            ];

            $this->sendTrackedTemplateMail(
                recipient: $recipient,
                template: $template,
                tenant: $tenant,
                toEmail: $email,
                recipientType: 'email',
                memberId: null,
                contactId: null,
                recipientName: $email,
                recipientReference: $email,
                fromAddress: $fromAddress,
                fromName: $fromName,
                replyToAddress: $replyToAddress,
                meta: [
                    'source' => 'manual_email',
                ],
            );

            $sentCount++;
        }

        $message = $sentCount . ' Serienmails gesendet';

        if ($skippedCount > 0) {
            $message .= ' · ' . $skippedCount . ' ohne Versand übersprungen';
        }

        return back()->with('success', $message);
    }

    private function splitDirectEmails(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        return collect(preg_split('/[\r\n,;]+/', (string) $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function sendTrackedTemplateMail(
        Member|Contact|array $recipient,
        Template $template,
        $tenant,
        string $toEmail,
        string $recipientType,
        ?int $memberId,
        ?int $contactId,
        string $recipientName,
        string $recipientReference,
        string $fromAddress,
        string $fromName,
        ?string $replyToAddress = null,
        array $meta = [],
    ): void {
        $html = TemplateParser::parse($template->body, $recipient, $tenant);

        $dispatchLog = TemplateDispatchLog::create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'created_by' => auth()->id(),
            'channel' => 'mail',
            'action' => 'sent',
            'recipient_type' => $recipientType,
            'member_id' => $memberId,
            'contact_id' => $contactId,
            'recipient_name' => $recipientName,
            'recipient_reference' => $recipientReference,
            'subject' => $template->subject ?: 'Nachricht',
            'message_excerpt' => Str::limit(strip_tags($html), 240),
            'dispatched_at' => now(),
            'meta' => array_merge([
                'template_type' => $template->type,
            ], $meta),
        ]);

        $trackedHtml = $this->mailTrackingService->instrument($html, $dispatchLog);

        try {
            Mail::send('mail.layout', [
                'body' => $trackedHtml,
                'tenant' => $tenant,
            ], function ($mail) use ($toEmail, $template, $fromAddress, $fromName, $replyToAddress, $tenant, $recipientName) {
                $mail->to($toEmail, $recipientName !== $toEmail ? $recipientName : null)
                    ->subject($template->subject ?? 'Nachricht')
                    ->from($fromAddress, $fromName);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?? $fromName);
                }
            });
        } catch (Throwable $e) {
            $dispatchLog->delete();

            throw $e;
        }
    }
}
