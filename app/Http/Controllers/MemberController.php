<?php

namespace App\Http\Controllers;

use App\Models\MemberCredit;
use App\Models\Member;
use App\Models\MemberCommunicationLog;
use App\Models\CustomMemberField;
use App\Models\Invoice;
use App\Models\PublicFormSubmission;
use App\Models\Tag;
use App\Models\Membership;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Services\GermanIbanBicResolver;
use App\Services\MemberService;
use App\Services\MembershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function __construct(
        protected MemberService $memberService
    ) {
        // ✅ Hard-Limit: Neuanlage nur wenn Limit nicht erreicht
        $this->middleware('member.limit')->only(['create', 'store']);
    }

    public function index()
    {
        $tenantId = app('currentTenant')->id;
        $today = now()->toDateString();
        $exitScope = request('exit_scope', 'kuendigungen');
        $exitWindowDays = (int) request('exit_days', 90);
        if (!in_array($exitWindowDays, [30, 60, 90, 180], true)) {
            $exitWindowDays = 90;
        }
        $upcomingExitWindow = now()->copy()->addDays($exitWindowDays)->toDateString();

        $query = Member::query()
            ->with(['tags', 'membership'])
            ->where('tenant_id', $tenantId);

        if (request()->filled('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (request()->filled('tag')) {
            $tagId = request('tag');
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
        }

        if (request()->filled('membership')) {
            $query->where('membership_id', request('membership'));
        }

        if (request()->filled('status')) {
            $status = request('status');

            if ($status === 'aktiv') {
                $query
                    ->whereDate('entry_date', '<=', $today)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('exit_date')
                            ->orWhereDate('exit_date', '>', $today);
                    });
            } elseif ($status === 'ehemalig') {
                $query->whereNotNull('exit_date')
                    ->whereDate('exit_date', '<=', $today);
            } elseif ($status === 'zukünftig') {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('entry_date')
                        ->orWhereDate('entry_date', '>', $today);
                });
            } elseif ($status === 'archiviert') {
                $query->whereNotNull('archived_at');
            }
        }

        if (!request()->filled('status') || request('status') !== 'archiviert') {
            $query->whereNull('archived_at');
        }

        $sortField = request('sort', 'last_name');
        $sortDirection = request('direction', 'asc');
        $allowedFields = ['first_name', 'last_name', 'email', 'member_id', 'entry_date', 'city'];
        if (!in_array($sortField, $allowedFields)) {
            $sortField = 'last_name';
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $members = $query
            ->orderBy($sortField, $sortDirection)
            ->paginate(25)
            ->withQueryString();

        $filteredMemberIds = (clone $query)
            ->orderBy($sortField, $sortDirection)
            ->pluck('id')
            ->all();

        $allTags = Tag::where('tenant_id', $tenantId)->orderBy('name')->get();
        $memberships = Membership::where('tenant_id', $tenantId)->orderBy('name')->get();

        $activeMembersBaseQuery = Member::where('tenant_id', $tenantId)
            ->whereNull('archived_at');

        $stats = [
            'alle' => (clone $activeMembersBaseQuery)->count(),
            'aktiv' => (clone $activeMembersBaseQuery)
                ->whereDate('entry_date', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('exit_date')
                        ->orWhereDate('exit_date', '>', $today);
                })
                ->count(),
            'ehemalig' => (clone $activeMembersBaseQuery)
                ->whereNotNull('exit_date')
                ->whereDate('exit_date', '<=', $today)
                ->count(),
            'zukünftig' => (clone $activeMembersBaseQuery)
                ->where(function ($q) use ($today) {
                    $q->whereNull('entry_date')
                        ->orWhereDate('entry_date', '>', $today);
                })
                ->count(),
            'archiviert' => Member::where('tenant_id', $tenantId)
                ->whereNotNull('archived_at')
                ->count(),
            'austritte_bald' => Member::where('tenant_id', $tenantId)
                ->whereNull('archived_at')
                ->whereNotNull('exit_date')
                ->whereDate('exit_date', '>', $today)
                ->whereDate('exit_date', '<=', $upcomingExitWindow)
                ->count(),
            'gekuendigt' => Member::where('tenant_id', $tenantId)
                ->whereNull('archived_at')
                ->whereNotNull('exit_date')
                ->whereDate('exit_date', '>', $today)
                ->count(),
        ];

        $exitQuery = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->whereNotNull('exit_date')
            ->with('membership')
            ->orderBy('exit_date');

        if ($exitScope === 'kuendigungen') {
            $exitQuery->whereDate('exit_date', '>', $today);
        } elseif ($exitScope === 'zeitraum') {
            $exitQuery->whereDate('exit_date', '>', $today)
                ->whereDate('exit_date', '<=', $upcomingExitWindow);
        } elseif ($exitScope === 'vergangen') {
            $exitQuery->whereDate('exit_date', '<=', $today);
        }

        $upcomingExits = $exitQuery
            ->take(8)
            ->get();

        return view('members.index', compact(
            'members',
            'sortField',
            'sortDirection',
            'allTags',
            'memberships',
            'stats',
            'filteredMemberIds',
            'upcomingExits',
            'exitScope',
            'exitWindowDays'
        ));
    }

    public function create(MembershipService $membershipService)
    {
        $memberships = $membershipService->getForTenant();
        $allTags = Tag::where('tenant_id', app('currentTenant')->id)->orderBy('name')->get();

        return view('members.create', compact('memberships', 'allTags'));
    }

    public function lookupBic(Request $request, GermanIbanBicResolver $resolver)
    {
        $validated = $request->validate([
            'iban' => ['required', 'string', 'max:34'],
        ]);

        $normalizedIban = $resolver->normalizeIban($validated['iban']);
        $resolved = $resolver->resolve($normalizedIban, auth()->user()->tenant_id);

        return response()->json([
            'found' => (bool) $resolved,
            'iban' => $normalizedIban,
            'bic' => $resolved['bic'] ?? null,
            'source' => $resolved['source'] ?? null,
            'source_label' => $resolved['source_label'] ?? null,
        ]);
    }

    public function store(StoreMemberRequest $request)
    {
        $member = $this->memberService->create($request);

        if ($request->filled('tags')) {
            $member->tags()->sync($request->input('tags'));
        }

        return redirect()->route('members.index')->with('success', 'Mitglied erfolgreich hinzugefügt.');
    }

    public function show(Member $member)
    {
        $this->authorizeMember($member);
        $member->load(['customValues', 'membership', 'tags']);

        $customFields = CustomMemberField::where('tenant_id', $member->tenant_id)
            ->where('visible', true)
            ->orderBy('order')
            ->get();

        $invoices = Invoice::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->with('items')
            ->latest('invoice_date')
            ->take(8)
            ->get();

        $eventRegistrations = PublicFormSubmission::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereNotNull('event_id')
            ->with('event')
            ->where(function ($query) use ($member) {
                $query->where('member_id', $member->id);

                if ($member->email) {
                    $query->orWhere('email', $member->email);
                }
            })
            ->latest()
            ->take(8)
            ->get()
            ->unique(fn ($submission) => $submission->id)
            ->values();

        $communicationLogs = $member->communicationLogs()
            ->with('creator')
            ->take(15)
            ->get();

        $credits = $member->credits()
            ->with(['creator', 'applications.invoice'])
            ->take(12)
            ->get();

        $activity = collect()
            ->merge($communicationLogs->map(fn ($log) => [
                'type' => 'communication',
                'title' => 'Kommunikation ' . strtoupper($log->channel ?: 'allgemein'),
                'subtitle' => $log->subject ?: \Illuminate\Support\Str::limit($log->message ?: 'ohne Betreff', 80),
                'meta' => ($log->direction === 'incoming' ? 'Eingang' : 'Ausgang') . ($log->creator ? ' · ' . $log->creator->name : ''),
                'date' => $log->sent_at ?? $log->created_at,
                'route' => null,
            ]))
            ->merge($invoices->map(fn ($invoice) => [
                'type' => 'invoice',
                'title' => 'Rechnung ' . $invoice->invoice_number,
                'subtitle' => optional($invoice->invoice_date)->format('d.m.Y') ?: 'ohne Datum',
                'meta' => ucfirst($invoice->status ?? 'entwurf'),
                'date' => $invoice->invoice_date ?? $invoice->created_at,
                'route' => route('invoices.show', $invoice),
            ]))
            ->merge($eventRegistrations->map(fn ($registration) => [
                'type' => 'event',
                'title' => $registration->event?->title ?: 'Event-Anmeldung',
                'subtitle' => 'Anmeldung eingegangen',
                'meta' => optional($registration->created_at)->format('d.m.Y H:i'),
                'date' => $registration->created_at,
                'route' => $registration->event ? route('events.show', $registration->event) : null,
            ]))
            ->sortByDesc('date')
            ->take(10)
            ->values();

        $memberStats = [
            'invoice_total' => $invoices->count(),
            'invoice_open' => $invoices->where('status', 'open')->count(),
            'event_registrations' => $eventRegistrations->count(),
            'tags' => $member->tags->count(),
            'communication_logs' => $communicationLogs->count(),
            'credit_balance' => round((float) $credits->sum('remaining_amount'), 2),
            'credit_total' => round((float) $credits->sum('amount'), 2),
            'is_archived' => $member->is_archived,
        ];

        return view('members.show', compact(
            'member',
            'customFields',
            'invoices',
            'eventRegistrations',
            'activity',
            'memberStats',
            'communicationLogs',
            'credits'
        ));
    }

    public function storeCredit(Request $request, Member $member)
    {
        $this->authorizeMember($member);
        abort_unless(auth()->user()?->canManageFinance(), 403);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'credited_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        MemberCredit::create([
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'created_by' => auth()->id(),
            'description' => $validated['description'],
            'notes' => $validated['notes'] ?? null,
            'amount' => $validated['amount'],
            'remaining_amount' => $validated['amount'],
            'credited_at' => $validated['credited_at'] ?? now()->toDateString(),
        ]);

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Guthaben wurde beim Mitglied hinterlegt.');
    }

    public function photo(Member $member): StreamedResponse
    {
        $this->authorizeMember($member);

        abort_unless($member->photo && Storage::disk('public')->exists($member->photo), 404);

        return Storage::disk('public')->response($member->photo);
    }

    public function edit(Member $member, MembershipService $membershipService)
    {
        $this->authorizeMember($member);
        $memberships = $membershipService->getForTenant();
        $member->load('customValues');

        $customFields = CustomMemberField::where('tenant_id', $member->tenant_id)
            ->where('visible', true)
            ->orderBy('order')
            ->get();

        $allTags = Tag::where('tenant_id', $member->tenant_id)->orderBy('name')->get();

        return view('members.edit', compact('member', 'memberships', 'customFields', 'allTags'));
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $this->authorizeMember($member);
        $this->memberService->update($request, $member);

        if ($request->has('tags')) {
            $member->tags()->sync($request->input('tags'));
        } else {
            $member->tags()->sync([]);
        }

        return redirect()->route('members.index')->with('success', 'Mitglied erfolgreich aktualisiert.');
    }

    public function destroy(Member $member)
    {
        $this->authorizeMember($member);

        $member->update([
            'archived_at' => now(),
            'deletion_requested_at' => $member->deletion_requested_at ?? now(),
        ]);

        return redirect()->route('members.index')->with('success', 'Mitglied wurde archiviert.');
    }

    public function restore(Member $member)
    {
        $this->authorizeMember($member);

        $member->update([
            'archived_at' => null,
        ]);

        return redirect()->route('members.show', $member)->with('success', 'Mitglied wurde reaktiviert.');
    }

    public function exportDatenauskunft(Member $member)
    {
        $this->authorizeMember($member);

        $pdf = Pdf::loadView('members.pdf.datenauskunft', ['member' => $member]);

        return $pdf->download("Datenauskunft_{$member->last_name}_{$member->id}.pdf");
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'selected' => 'required|array',
            'selected.*' => 'exists:members,id',
            'action' => 'required|string|in:set_status_aktiv,set_status_zukuenftig,set_status_ehemalig,assign_membership,delete',
            'membership_id' => 'nullable|integer|exists:memberships,id',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $members = Member::whereIn('id', $request->selected)
            ->where('tenant_id', $tenantId)
            ->get();

        $membership = null;

        if ($request->action === 'assign_membership') {
            if (! $request->filled('membership_id')) {
                return redirect()->route('members.index')
                    ->with('error', 'Bitte wähle eine Mitgliedschaft für die Massenverarbeitung aus.');
            }

            $membership = Membership::query()
                ->where('tenant_id', $tenantId)
                ->find($request->integer('membership_id'));

            if (! $membership) {
                return redirect()->route('members.index')
                    ->with('error', 'Die ausgewählte Mitgliedschaft gehört nicht zu diesem Verein.');
            }
        }

        $count = 0;

        foreach ($members as $member) {
            switch ($request->action) {
                case 'set_status_aktiv':
                    if (!$member->entry_date || $member->entry_date->isFuture()) {
                        $member->entry_date = now()->toDateString();
                    }
                    $member->exit_date = null;
                    $member->termination_date = null;
                    $member->save();
                    $count++;
                    break;

                case 'set_status_zukuenftig':
                    $member->entry_date = now()->addDay()->toDateString();
                    $member->exit_date = null;
                    $member->save();
                    $count++;
                    break;

                case 'set_status_ehemalig':
                    $member->exit_date = now()->toDateString();
                    $member->save();
                    $count++;
                    break;

                case 'assign_membership':
                    $member->membership_id = $membership->id;
                    $member->membership_amount = $membership->amount;
                    $member->membership_interval = $membership->interval;
                    $member->save();
                    $count++;
                    break;

                case 'delete':
                    $member->archived_at = now();
                    $member->deletion_requested_at = $member->deletion_requested_at ?? now();
                    $member->save();
                    $count++;
                    break;
            }
        }

        return redirect()->route('members.index')
            ->with('success', "{$count} Mitglied(er) wurden erfolgreich bearbeitet.");
    }

    public function storeCommunicationLog(Request $request, Member $member)
    {
        $this->authorizeMember($member);

        $validated = $request->validate([
            'channel' => 'required|in:email,phone,whatsapp,post,personal,system',
            'direction' => 'required|in:incoming,outgoing',
            'recipient' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:4000',
            'sent_at' => 'nullable|date',
        ]);

        $sentAt = $validated['sent_at'] ?? now();

        MemberCommunicationLog::create([
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'created_by' => auth()->id(),
            'channel' => $validated['channel'],
            'direction' => $validated['direction'],
            'recipient' => $validated['recipient'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'] ?? null,
            'sent_at' => $sentAt,
        ]);

        $member->update(['last_contacted_at' => $sentAt]);

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Kommunikation wurde protokolliert.');
    }

    public function communicationExport(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,whatsapp',
            'member_ids' => 'nullable|string',
        ]);

        $memberIds = collect(explode(',', (string) $request->input('member_ids')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $members = Member::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->when($memberIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $memberIds))
            ->orderBy('last_name')
            ->get();

        $type = $request->input('type');
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mitglieder-' . $type . '-kontakte.csv"',
        ];

        $callback = function () use ($members, $type) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['mitgliedsnummer', 'name', 'email', 'telefon', 'whatsapp'], ';');

            foreach ($members as $member) {
                $whatsApp = $member->whatsapp_phone ?: $member->mobile;

                if ($type === 'email' && blank($member->email)) {
                    continue;
                }

                if ($type === 'whatsapp' && blank($whatsApp)) {
                    continue;
                }

                fputcsv($handle, [
                    $member->member_id,
                    $member->full_name,
                    $member->email,
                    $member->mobile ?: $member->landline,
                    $whatsApp,
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function authorizeMember(Member $member): void
    {
        abort_if($member->tenant_id !== app('currentTenant')->id, 403, 'Unberechtigter Zugriff.');
    }
}
