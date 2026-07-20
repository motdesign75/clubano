<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Member;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Services\TemplateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Mpdf\Mpdf;

class LetterController extends Controller
{
    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $templates = Template::where('tenant_id', $tenantId)
            ->whereIn('type', [Template::TYPE_LETTER, Template::TYPE_MAIL_AND_LETTER])
            ->orderBy('name')
            ->get();

        $members = Member::where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->orderBy('last_name')
            ->get();

        $contacts = Contact::where('tenant_id', $tenantId)
            ->orderBy('last_name')
            ->orderBy('organization')
            ->get();

        return view('letters.create', [
            'templates' => $templates,
            'members' => $members,
            'contacts' => $contacts,
            'selectedTemplateId' => $request->integer('template') ?: optional($templates->first())->id,
            'preselectedMembers' => collect(explode(',', (string) $request->query('members')))->filter()->map(fn ($id) => (int) $id)->values()->all(),
            'preselectedContacts' => collect(explode(',', (string) $request->query('contacts')))->filter()->map(fn ($id) => (int) $id)->values()->all(),
        ]);
    }

    public function generate(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'template_id' => [
                'required',
                Rule::exists('templates', 'id')->where(function ($query) use ($tenant) {
                    $query->where('tenant_id', $tenant->id)
                        ->whereIn('type', [Template::TYPE_LETTER, Template::TYPE_MAIL_AND_LETTER]);
                }),
            ],
            'recipient_type' => ['required', Rule::in(['member', 'contact', 'free'])],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', Rule::exists('members', 'id')->where('tenant_id', $tenant->id)],
            'contacts' => ['nullable', 'array'],
            'contacts.*' => ['integer', Rule::exists('contacts', 'id')->where('tenant_id', $tenant->id)],
            'free_name' => ['nullable', 'string', 'max:255'],
            'free_organization' => ['nullable', 'string', 'max:255'],
            'free_salutation' => ['nullable', 'string', 'max:255'],
            'free_street' => ['nullable', 'string', 'max:255'],
            'free_zip' => ['nullable', 'string', 'max:20'],
            'free_city' => ['nullable', 'string', 'max:255'],
            'free_country' => ['nullable', 'string', 'max:255'],
        ]);

        $template = Template::where('tenant_id', $tenant->id)
            ->whereIn('type', [Template::TYPE_LETTER, Template::TYPE_MAIL_AND_LETTER])
            ->findOrFail($validated['template_id']);

        if ($validated['recipient_type'] === 'free') {
            $missing = collect([
                'free_name' => 'Name',
                'free_street' => 'Straße',
                'free_zip' => 'PLZ',
                'free_city' => 'Ort',
            ])->filter(fn ($label, $field) => blank($validated[$field] ?? null))->values();

            if ($missing->isNotEmpty()) {
                return back()->withErrors([
                    'recipient_type' => 'Für freie Briefadressen fehlen: ' . $missing->implode(', '),
                ])->withInput();
            }
        }

        $letters = $this->resolveRecipients($validated, $tenant->id, $tenant, $template);

        if (count($letters) === 0) {
            return back()->withErrors([
                'recipient_type' => 'Bitte wähle mindestens einen gültigen Briefempfänger aus.',
            ])->withInput();
        }

        foreach ($letters as $letter) {
            TemplateDispatchLog::create([
                'tenant_id' => $tenant->id,
                'template_id' => $template->id,
                'created_by' => auth()->id(),
                'channel' => 'letter',
                'action' => 'generated',
                'recipient_type' => $letter['recipient_type'],
                'member_id' => $letter['member_id'],
                'contact_id' => $letter['contact_id'],
                'recipient_name' => $letter['display_name'],
                'recipient_reference' => implode(', ', $letter['address_lines']),
                'subject' => $template->subject ?: $template->name,
                'message_excerpt' => Str::limit(strip_tags($letter['body']), 240),
                'dispatched_at' => now(),
                'meta' => [
                    'template_type' => $template->type,
                    'briefbogen_aktiv' => (bool) ($tenant->use_letterhead && $tenant->pdf_template),
                ],
            ]);
        }

        $fileName = 'briefe-' . Str::slug($template->name ?: 'vorlage') . '-' . now()->format('Ymd-His') . '.pdf';

        return response($this->renderPdf($letters, $tenant, $template), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    private function resolveRecipients(array $validated, int $tenantId, $tenant, Template $template): array
    {
        return match ($validated['recipient_type']) {
            'member' => Member::where('tenant_id', $tenantId)
                ->whereIn('id', $validated['members'] ?? [])
                ->orderBy('last_name')
                ->get()
                ->map(fn (Member $member) => $this->letterPayloadFromMember($member, $tenant, $template))
                ->all(),
            'contact' => Contact::where('tenant_id', $tenantId)
                ->whereIn('id', $validated['contacts'] ?? [])
                ->orderBy('last_name')
                ->orderBy('organization')
                ->get()
                ->map(fn (Contact $contact) => $this->letterPayloadFromContact($contact, $tenant, $template))
                ->all(),
            'free' => [$this->letterPayloadFromArray([
                'tenant_id' => $tenantId,
                'name' => $validated['free_name'] ?? '',
                'organization' => $validated['free_organization'] ?? '',
                'salutation' => $validated['free_salutation'] ?? '',
                'street' => $validated['free_street'] ?? '',
                'zip' => $validated['free_zip'] ?? '',
                'city' => $validated['free_city'] ?? '',
                'country' => $validated['free_country'] ?? '',
            ], $tenant, $template)],
            default => [],
        };
    }

    private function letterPayloadFromMember(Member $member, $tenant, Template $template): array
    {
        $body = TemplateParser::parse($template->body, $member, $tenant);

        return [
            'display_name' => $member->full_name,
            'recipient_type' => 'member',
            'member_id' => $member->id,
            'contact_id' => null,
            'address_lines' => array_values(array_filter([
                $member->full_name,
                trim((string) ($member->care_of ?? '')) ?: null,
                trim((string) ($member->street ?? '')),
                trim((string) (($member->zip ?? '') . ' ' . ($member->city ?? ''))),
                trim((string) ($member->country ?? '')),
            ])),
            'body' => $body,
        ];
    }

    private function letterPayloadFromContact(Contact $contact, $tenant, Template $template): array
    {
        $body = TemplateParser::parse($template->body, $contact, $tenant);
        $nameLine = $contact->organization ?: $contact->company ?: $contact->full_name;
        $personLine = $contact->organization && $contact->full_name ? $contact->full_name : null;

        return [
            'display_name' => $contact->display_name,
            'recipient_type' => 'contact',
            'member_id' => null,
            'contact_id' => $contact->id,
            'address_lines' => array_values(array_filter([
                $nameLine,
                $personLine,
                trim((string) ($contact->care_of ?? '')) ?: null,
                trim((string) ($contact->street ?? '')),
                trim((string) (($contact->zip ?? '') . ' ' . ($contact->city ?? ''))),
                trim((string) ($contact->country ?? '')),
            ])),
            'body' => $body,
        ];
    }

    private function letterPayloadFromArray(array $recipient, $tenant, Template $template): array
    {
        $body = TemplateParser::parse($template->body, $recipient, $tenant);
        $headline = trim((string) ($recipient['organization'] ?? '')) ?: trim((string) ($recipient['name'] ?? ''));
        $nameLine = !empty($recipient['organization']) && !empty($recipient['name']) ? $recipient['name'] : null;

        return [
            'display_name' => $headline,
            'recipient_type' => 'free',
            'member_id' => null,
            'contact_id' => null,
            'address_lines' => array_values(array_filter([
                $headline,
                $nameLine,
                trim((string) ($recipient['street'] ?? '')),
                trim((string) (($recipient['zip'] ?? '') . ' ' . ($recipient['city'] ?? ''))),
                trim((string) ($recipient['country'] ?? '')),
            ])),
            'body' => $body,
        ];
    }

    private function renderPdf(array $letters, $tenant, Template $template): string
    {
        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 22,
            'margin_bottom' => 20,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->SetTitle('Serienbrief ' . ($template->name ?: 'Vorlage'));
        $mpdf->SetAuthor($tenant->name ?? 'Clubano');
        $mpdf->SetAutoPageBreak(true, 20);

        $letterheadPdfTemplateId = null;
        $letterheadImagePath = null;

        if ($tenant->use_letterhead && $tenant->pdf_template) {
            $path = storage_path('app/public/' . $tenant->pdf_template);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (is_file($path)) {
                if ($extension == 'pdf') {
                    try {
                        $mpdf->setSourceFile($path);
                        $letterheadPdfTemplateId = $mpdf->importPage(1);
                    } catch (\Throwable $exception) {
                        $letterheadPdfTemplateId = null;
                    }
                } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                    $letterheadImagePath = $path;
                }
            }
        }

        foreach ($letters as $index => $letter) {
            if ($index > 0) {
                $mpdf->AddPage();
            }

            if ($letterheadPdfTemplateId) {
                $mpdf->useTemplate($letterheadPdfTemplateId);
            }

            $html = view('letters.pdf', [
                'tenant' => $tenant,
                'template' => $template,
                'letter' => $letter,
                'letterheadImagePath' => $letterheadImagePath,
                'showLetterheadImage' => ! $letterheadPdfTemplateId && ! empty($letterheadImagePath),
            ])->render();

            $mpdf->WriteHTML($html);
        }

        return $mpdf->Output('', 'S');
    }
}
