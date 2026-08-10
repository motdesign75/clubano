<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperatorAnnouncement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OperatorAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = OperatorAnnouncement::query()
            ->withCount('deliveries')
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

        $body = old('body_markdown', $this->defaultBody());

        return view('admin.announcements.create', [
            'tenantOptions' => $tenantOptions,
            'recipientFilters' => $this->recipientFilters(),
            'previewHtml' => $this->renderBody($body),
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
            'recipient_filter' => ['required', Rule::in(array_keys($this->recipientFilters()))],
            'tenant_ids' => ['nullable', 'array'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')],
        ]);

        $bodyHtml = $this->renderBody($validated['body_markdown']);
        $recipientQuery = $this->recipientQuery($validated['recipient_filter'], $validated['tenant_ids'] ?? []);
        $recipients = $recipientQuery->get();

        if ($validated['action'] === 'send' && $recipients->isEmpty()) {
            return back()
                ->withInput()
                ->with('error', 'Für diese Auswahl wurden keine Vereinsadmins gefunden.');
        }

        $announcement = OperatorAnnouncement::create([
            'created_by' => $request->user()->id,
            'subject' => $validated['subject'],
            'body_markdown' => $validated['body_markdown'],
            'body_html' => $bodyHtml,
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
            'recipient_filter' => $validated['recipient_filter'],
            'recipient_summary' => [
                'filter' => $this->recipientFilters()[$validated['recipient_filter']],
                'tenant_ids' => $validated['tenant_ids'] ?? [],
                'recipient_count' => $validated['action'] === 'send' ? $recipients->count() : 1,
            ],
            'status' => $validated['action'] === 'send' ? 'sending' : 'test',
        ]);

        if ($validated['action'] === 'test') {
            $this->sendMail($request->user()->email, $request->user()->name, null, null, $announcement);

            return redirect()
                ->route('admin.announcements.index')
                ->with('success', 'Testmail wurde an dein Betreiberkonto gesendet.');
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            try {
                $this->sendMail($recipient->email, $recipient->name, $recipient->tenant, $recipient, $announcement);
                $announcement->deliveries()->create([
                    'tenant_id' => $recipient->tenant_id,
                    'user_id' => $recipient->id,
                    'recipient_name' => $recipient->name,
                    'email' => $recipient->email,
                    'status' => 'sent',
                ]);
                $sent++;
            } catch (Throwable $e) {
                $announcement->deliveries()->create([
                    'tenant_id' => $recipient->tenant_id,
                    'user_id' => $recipient->id,
                    'recipient_name' => $recipient->name,
                    'email' => $recipient->email,
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
            ]),
        ]);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', "Betreiber-Mitteilung versendet: {$sent} erfolgreich, {$failed} fehlgeschlagen.");
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
            'selected' => 'Manuell ausgewählte Vereine',
        ];
    }

    private function recipientQuery(string $filter, array $tenantIds)
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
            ->when($filter === 'selected', fn ($query) => $query->whereIn('id', $tenantIds));

        return User::query()
            ->with('tenant')
            ->whereIn('tenant_id', $tenantQuery)
            ->where('role', User::ROLE_ADMIN)
            ->whereNotNull('email')
            ->orderBy('tenant_id')
            ->orderBy('name');
    }

    private function renderBody(string $body): string
    {
        $html = Str::markdown(e($body), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return (string) $html;
    }

    private function sendMail(string $email, ?string $name, ?Tenant $tenant, ?User $recipient, OperatorAnnouncement $announcement): void
    {
        Mail::send('admin.announcements.mail', [
            'announcement' => $announcement,
            'tenant' => $tenant,
            'recipient' => $recipient,
        ], function ($mail) use ($email, $name, $announcement) {
            $mail->to($email, $name)
                ->subject($announcement->subject)
                ->from(config('mail.from.address'), config('mail.from.name', 'Clubano'));
        });
    }

    private function defaultBody(): string
    {
        return <<<MARKDOWN
Hallo,

wir haben Clubano verbessert, damit die Arbeit im Verein einfacher und sicherer wird.

## Neu in Clubano

- Punkt eins kurz und klar beschreiben
- Punkt zwei mit konkretem Nutzen
- Punkt drei, falls wichtig

**Unser Tipp:** Öffne Clubano und schau dir die neuen Möglichkeiten direkt im Verein an.

Viele Grüße
Maik-Oliver von Clubano
MARKDOWN;
    }
}
