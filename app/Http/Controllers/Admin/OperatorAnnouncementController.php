<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperatorAnnouncement;
use App\Models\OperatorAnnouncementDelivery;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\OperatorAuditLogger;
use DOMDocument;
use DOMElement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OperatorAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = OperatorAnnouncement::query()
            ->withCount('deliveries')
            ->withCount([
                'deliveries as opened_count' => fn ($query) => $query->where('open_count', '>', 0),
                'deliveries as clicked_count' => fn ($query) => $query->where('click_count', '>', 0),
            ])
            ->latest()
            ->paginate(12);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(Request $request): View
    {
        $tenantOptions = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        $recipientOptions = Tenant::query()
            ->whereHas('users', function ($query) {
                $query
                    ->where('role', User::ROLE_ADMIN)
                    ->whereNotNull('email');
            })
            ->with(['users' => function ($query) {
                $query
                    ->where('role', User::ROLE_ADMIN)
                    ->whereNotNull('email')
                    ->orderBy('name')
                    ->select(['id', 'tenant_id', 'name', 'email']);
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        $body = old('body_markdown', $this->defaultBody());

        return view('admin.announcements.create', [
            'tenantOptions' => $tenantOptions,
            'recipientOptions' => $recipientOptions,
            'recipientFilters' => $this->recipientFilters(),
            'categories' => $this->categories(),
            'previewHtml' => $this->sanitizeBody($body),
            'defaultBody' => $body,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['test', 'send'])],
            'subject' => ['required', 'string', 'max:180'],
            'body_markdown' => ['required', 'string', 'max:12000'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'url', 'max:2048'],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'test_email' => ['nullable', 'email', 'max:255'],
            'recipient_filter' => ['required', Rule::in(array_keys($this->recipientFilters()))],
            'tenant_ids' => ['nullable', 'array'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')],
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $bodyHtml = $this->sanitizeBody($validated['body_markdown']);
        $recipientQuery = $this->recipientQuery(
            $validated['recipient_filter'],
            $validated['tenant_ids'] ?? [],
            $validated['recipient_user_ids'] ?? []
        );
        $candidateRecipients = $recipientQuery->get();
        $excludedByOptOut = 0;
        $recipients = $candidateRecipients;

        if ($validated['category'] === OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE) {
            $recipients = $candidateRecipients
                ->filter(fn (User $recipient) => $recipient->operator_updates_unsubscribed_at === null)
                ->values();
            $excludedByOptOut = $candidateRecipients->count() - $recipients->count();
        }

        if ($validated['action'] === 'send' && $recipients->isEmpty()) {
            return back()
                ->withInput()
                ->with('error', $excludedByOptOut > 0
                    ? 'Für diese Auswahl bleiben keine zustellbaren Vereinsadmins übrig. Einige Empfänger haben Produktupdates abbestellt.'
                    : 'Für diese Auswahl wurden keine Vereinsadmins gefunden.');
        }

        $announcement = OperatorAnnouncement::create([
            'created_by' => $request->user()->id,
            'subject' => $validated['subject'],
            'body_markdown' => $validated['body_markdown'],
            'body_html' => $bodyHtml,
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
            'category' => $validated['category'],
            'recipient_filter' => $validated['recipient_filter'],
            'recipient_summary' => [
                'filter' => $this->recipientFilters()[$validated['recipient_filter']],
                'category' => $this->categories()[$validated['category']],
                'tenant_ids' => $validated['tenant_ids'] ?? [],
                'recipient_user_ids' => $validated['recipient_user_ids'] ?? [],
                'recipient_count' => $validated['action'] === 'send' ? $recipients->count() : 1,
                'excluded_by_opt_out' => $validated['action'] === 'send' ? $excludedByOptOut : 0,
            ],
            'status' => $validated['action'] === 'send' ? 'sending' : 'test',
        ]);

        if ($validated['action'] === 'test') {
            $testEmail = $validated['test_email'] ?? $request->user()->email;

            try {
                $this->sendMail($testEmail, $request->user()->name, null, null, $announcement, null);
            } catch (Throwable $e) {
                $announcement->update([
                    'status' => 'failed',
                    'recipient_summary' => array_merge($announcement->recipient_summary ?? [], [
                        'failed' => 1,
                        'error' => Str::limit($e->getMessage(), 1000),
                    ]),
                ]);

                return back()
                    ->withInput()
                    ->with('error', 'Die Testmail konnte nicht versendet werden. Bitte prüfe die Mail-Einstellungen oder den Inhalt der Nachricht.');
            }

            app(OperatorAuditLogger::class)->log(
                $request,
                'operator.announcement.test_sent',
                'Testmail versendet',
                null,
                [
                    'subject' => $announcement->subject,
                    'category' => $announcement->category,
                    'test_email' => $testEmail,
                ]
            );

            return redirect()
                ->route('admin.announcements.index')
                ->with('success', 'Testmail wurde an dein Betreiberkonto gesendet.');
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $delivery = $announcement->deliveries()->create([
                'tenant_id' => $recipient->tenant_id,
                'user_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'email' => $recipient->email,
                'status' => 'sending',
            ]);

            try {
                $this->sendMail($recipient->email, $recipient->name, $recipient->tenant, $recipient, $announcement, $delivery);
                $delivery->update([
                    'status' => 'sent',
                ]);
                $sent++;
            } catch (Throwable $e) {
                $delivery->update([
                    'status' => 'failed',
                    'error' => Str::limit($e->getMessage(), 1000),
                ]);
                $failed++;
            }
        }

        $announcement->update([
            'status' => $failed > 0 ? 'sent_with_errors' : 'sent',
            'sent_at' => now(),
            'recipient_summary' => array_merge($announcement->recipient_summary ?? [], [
                'sent' => $sent,
                'failed' => $failed,
                'excluded_by_opt_out' => $excludedByOptOut,
            ]),
        ]);

        app(OperatorAuditLogger::class)->log(
            $request,
            'operator.announcement.sent',
            'Betreiber-Mitteilung versendet',
            null,
            [
                'subject' => $announcement->subject,
                'category' => $announcement->category,
                'recipient_filter' => $announcement->recipient_filter,
                'sent' => $sent,
                'failed' => $failed,
                'excluded_by_opt_out' => $excludedByOptOut,
                'tenant_ids' => $validated['tenant_ids'] ?? [],
                'recipient_user_ids' => $validated['recipient_user_ids'] ?? [],
            ]
        );

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', "Betreiber-Mitteilung versendet: {$sent} erfolgreich, {$failed} fehlgeschlagen, {$excludedByOptOut} wegen Abmeldung ausgeschlossen.");
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'image', 'max:5120'],
        ]);

        $path = $validated['file']->store('operator-announcements/' . now()->format('Y/m'), 'public');

        return response()->json([
            'location' => asset(Storage::url($path)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function recipientFilters(): array
    {
        return [
            'all_active' => 'Alle Vereine mit aktivem Zugriff',
            'complimentary' => 'Pilot- und Freilizenzen',
            'without_members' => 'Vereine ohne Mitglieder',
            'import_issues' => 'Vereine mit Importbedarf',
            'selected' => 'Einzelne Empfänger auswählen',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function categories(): array
    {
        return [
            OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE => 'Produktupdate / Neuigkeiten',
            OperatorAnnouncement::CATEGORY_SECURITY => 'Wartung / Sicherheit',
            OperatorAnnouncement::CATEGORY_CONTRACT => 'Vertrag / Abrechnung',
            OperatorAnnouncement::CATEGORY_PRIVACY => 'Datenschutz / AVV',
        ];
    }

    private function recipientQuery(string $filter, array $tenantIds, array $recipientUserIds = [])
    {
        $tenantQuery = Tenant::query()
            ->select('tenants.id')
            ->when($filter === 'all_active', function ($query) {
                $query->where(function ($accessQuery) {
                    $accessQuery
                        ->whereIn('license_mode', ['beta', 'gifted'])
                        ->orWhereNotNull('stripe_id')
                        ->orWhere('trial_ends_at', '>=', now());
                });
            })
            ->when($filter === 'complimentary', fn ($query) => $query->whereIn('license_mode', ['beta', 'gifted']))
            ->when($filter === 'without_members', function ($query) {
                $query->whereNotExists(function ($memberQuery) {
                    $memberQuery
                        ->selectRaw('1')
                        ->from('members')
                        ->whereColumn('members.tenant_id', 'tenants.id');
                });
            })
            ->when($filter === 'import_issues', function ($query) {
                $query->where(function ($issueQuery) {
                    $issueQuery
                        ->whereExists(function ($importQuery) {
                            $importQuery
                                ->selectRaw('1')
                                ->from('import_runs')
                                ->whereColumn('import_runs.tenant_id', 'tenants.id')
                                ->where('status', '!=', 'completed');
                        })
                        ->orWhereNotExists(function ($memberQuery) {
                            $memberQuery
                                ->selectRaw('1')
                                ->from('members')
                                ->whereColumn('members.tenant_id', 'tenants.id');
                        });
                });
            })
            ->when($filter === 'selected', fn ($query) => $query->whereIn('id', [-1]));

        return User::query()
            ->with('tenant')
            ->when(
                $filter === 'selected',
                fn ($query) => $query->whereIn('id', $recipientUserIds),
                fn ($query) => $query->whereIn('tenant_id', $tenantQuery)
            )
            ->where('role', User::ROLE_ADMIN)
            ->whereNotNull('email')
            ->orderBy('tenant_id')
            ->orderBy('name');
    }

    private function sanitizeBody(string $body): string
    {
        return app(HtmlSanitizer::class)->sanitize($body) ?? '';
    }

    private function sendMail(string $email, ?string $name, ?Tenant $tenant, ?User $recipient, OperatorAnnouncement $announcement, ?OperatorAnnouncementDelivery $delivery): void
    {
        $bodyHtml = $delivery
            ? $this->instrumentBody($announcement->body_html, $delivery)
            : $announcement->body_html;

        $ctaUrl = $announcement->cta_url;

        if ($delivery && $ctaUrl && $this->shouldTrackHref($ctaUrl)) {
            $ctaUrl = URL::signedRoute('operator-announcements.tracking.click', [
                'delivery' => $delivery->id,
                'target' => $ctaUrl,
            ]);
        }

        Mail::send('admin.announcements.mail', [
            'announcement' => $announcement,
            'tenant' => $tenant,
            'recipient' => $recipient,
            'bodyHtml' => $bodyHtml,
            'ctaUrl' => $ctaUrl,
            'unsubscribeUrl' => $this->unsubscribeUrl($announcement, $delivery),
        ], function ($mail) use ($email, $name, $announcement) {
            $mail->to($email, $name)
                ->subject($announcement->subject)
                ->from(config('mail.from.address'), config('mail.from.name', 'Clubano'));
        });
    }

    private function unsubscribeUrl(OperatorAnnouncement $announcement, ?OperatorAnnouncementDelivery $delivery): ?string
    {
        if ($announcement->category !== OperatorAnnouncement::CATEGORY_PRODUCT_UPDATE || ! $delivery || ! $delivery->user_id) {
            return null;
        }

        return URL::signedRoute('operator-announcements.unsubscribe', [
            'delivery' => $delivery->id,
            'token' => $delivery->tracking_token,
        ]);
    }

    private function instrumentBody(string $html, OperatorAnnouncementDelivery $delivery): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $encodedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        @$document->loadHTML(
            '<!DOCTYPE html><html><body><div id="tracked-root">' . $encodedHtml . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $root = $document->getElementById('tracked-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        foreach ($root->getElementsByTagName('a') as $link) {
            $href = trim((string) $link->getAttribute('href'));

            if (! $this->shouldTrackHref($href)) {
                continue;
            }

            $link->setAttribute('href', URL::signedRoute('operator-announcements.tracking.click', [
                'delivery' => $delivery->id,
                'target' => $href,
            ]));
        }

        $pixel = $document->createElement('img');
        $pixel->setAttribute('src', route('operator-announcements.tracking.open', $delivery->tracking_token));
        $pixel->setAttribute('alt', '');
        $pixel->setAttribute('width', '1');
        $pixel->setAttribute('height', '1');
        $pixel->setAttribute('style', 'display:block;border:0;outline:none;text-decoration:none;width:1px;height:1px;');
        $pixel->setAttribute('aria-hidden', 'true');

        $root->appendChild($pixel);

        $trackedHtml = '';

        foreach ($root->childNodes as $child) {
            $trackedHtml .= $document->saveHTML($child);
        }

        return $trackedHtml;
    }

    private function shouldTrackHref(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        if (str_starts_with($href, '#')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:')) {
            return false;
        }

        if (! filter_var($href, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(parse_url($href, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function defaultBody(): string
    {
        return <<<MARKDOWN
<p>Hallo,</p>

<p>wir haben Clubano verbessert, damit die Arbeit im Verein einfacher und sicherer wird.</p>

<h2>Neu in Clubano</h2>

<ul>
    <li>Punkt eins kurz und klar beschreiben</li>
    <li>Punkt zwei mit konkretem Nutzen</li>
    <li>Punkt drei, falls wichtig</li>
</ul>

<p><strong>Unser Tipp:</strong> Öffne Clubano und schau dir die neuen Möglichkeiten direkt im Verein an.</p>

<p>Viele Grüße<br>Maik-Oliver von Clubano</p>
MARKDOWN;
    }
}
