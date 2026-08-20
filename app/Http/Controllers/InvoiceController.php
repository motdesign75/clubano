<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Member;
use App\Models\MemberCredit;
use App\Models\MemberCreditApplication;
use App\Models\TemplateDispatchLog;
use App\Services\InvoicePdfService;
use App\Services\InvoiceCancellationService;
use App\Services\MailTrackingService;
use App\Services\TenantMailConfigurator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoicePdfService $invoicePdfService,
        private readonly InvoiceCancellationService $invoiceCancellationService,
        private readonly TenantMailConfigurator $tenantMailConfigurator,
        private readonly MailTrackingService $mailTrackingService,
    ) {
    }

    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        $status = trim((string) request('status', ''));
        $documentType = trim((string) request('document_type', ''));
        $recipientType = trim((string) request('recipient_type', ''));
        $search = trim((string) request('search', ''));
        $today = now()->startOfDay();

        $baseQuery = Invoice::query()
            ->where('tenant_id', $tenantId);

        $invoiceQuery = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->with(['member', 'contact', 'incomeAccount'])
            ->when($documentType !== '', fn ($q) => $q->where('document_type', $documentType))
            ->when($recipientType !== '', fn ($q) => $q->where('recipient_type', $recipientType))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where(function ($inner) use ($like) {
                    $inner->where('invoice_number', 'like', $like)
                        ->orWhere('recipient_name', 'like', $like)
                        ->orWhere('recipient_company', 'like', $like)
                        ->orWhere('recipient_email', 'like', $like)
                        ->orWhere('recipient_city', 'like', $like);
                });
            })
            ->latest();

        if ($status === 'overdue') {
            $invoiceQuery
                ->where('document_type', 'invoice')
                ->where('status', 'open')
                ->whereDate('due_date', '<', $today);
        } elseif ($status !== '') {
            $invoiceQuery->where('status', $status);
        }

        $invoices = $invoiceQuery->get();

        $stats = [
            'all' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)
                ->where('document_type', 'invoice')
                ->where('status', 'open')
                ->count(),
            'overdue' => (clone $baseQuery)
                ->where('document_type', 'invoice')
                ->where('status', 'open')
                ->whereDate('due_date', '<', $today)
                ->count(),
            'draft' => (clone $baseQuery)->where('status', 'entwurf')->count(),
            'paid' => (clone $baseQuery)->where('status', 'paid')->count(),
            'cancelled' => (clone $baseQuery)->where('status', 'storniert')->count(),
            'offers' => (clone $baseQuery)->where('document_type', 'offer')->count(),
            'due_total' => (clone $baseQuery)
                ->where('document_type', 'invoice')
                ->where('status', 'open')
                ->get()
                ->sum(fn (Invoice $invoice) => $invoice->getTotal()),
            'overdue_total' => (clone $baseQuery)
                ->where('document_type', 'invoice')
                ->where('status', 'open')
                ->whereDate('due_date', '<', $today)
                ->get()
                ->sum(fn (Invoice $invoice) => $invoice->getTotal()),
        ];

        return view('invoices.index', compact('invoices', 'stats', 'status', 'documentType', 'recipientType', 'search'));
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $documentType = $request->input('type') === 'offer' ? 'offer' : 'invoice';

        $members = Member::where('tenant_id', $tenantId)
            ->orderBy('last_name')
            ->get();

        $contacts = Contact::where('tenant_id', $tenantId)
            ->orderByRaw("COALESCE(organization, company, last_name, first_name)")
            ->get();

        $incomeAccounts = $this->incomeAccountsForTenant($tenantId);
        [$suggestedIncomeAccount, $incomeAccountHint] = $this->suggestIncomeAccount($documentType, $incomeAccounts);

        return view('invoices.create', compact('members', 'contacts', 'documentType', 'incomeAccounts', 'suggestedIncomeAccount', 'incomeAccountHint'));
    }

    public function edit(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        if (!$invoice->isDraft()) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Nur Entwuerfe koennen bearbeitet werden.');
        }

        $tenantId = auth()->user()->tenant_id;
        $documentType = $invoice->document_type;
        $members = Member::where('tenant_id', $tenantId)->orderBy('last_name')->get();
        $contacts = Contact::where('tenant_id', $tenantId)
            ->orderByRaw("COALESCE(organization, company, last_name, first_name)")
            ->get();

        $invoice->load(['items', 'incomeAccount']);
        $incomeAccounts = $this->incomeAccountsForTenant($tenantId);
        [$fallbackIncomeAccount, $incomeAccountHint] = $this->suggestIncomeAccount($documentType, $incomeAccounts);
        $suggestedIncomeAccount = $invoice->incomeAccount ?: $fallbackIncomeAccount;

        if ($invoice->incomeAccount) {
            $incomeAccountHint = 'Dieses Ertragskonto ist auf der Rechnung hinterlegt und wird spaeter automatisch fuer den Zahlungseingang verwendet.';
        }

        return view('invoices.create', compact('members', 'contacts', 'documentType', 'invoice', 'incomeAccounts', 'suggestedIncomeAccount', 'incomeAccountHint'));
    }

    public function storeMembershipInvoiceForMember(Member $member)
    {
        abort_if($member->tenant_id !== auth()->user()->tenant_id, 403);

        $member->loadMissing(['membership', 'familyPayer', 'familyMembers']);

        if ($member->family_payer_id) {
            return redirect()
                ->route('members.show', $member)
                ->with('error', 'Dieses Mitglied wird über ein anderes Mitglied abgerechnet. Bitte erstelle den Beitragsentwurf beim hinterlegten Zahler.');
        }

        $period = collect($this->getBillablePeriodsForMember($member))->last();

        if (!$period) {
            return redirect()
                ->route('members.show', $member)
                ->with('error', 'Für dieses Mitglied konnte aktuell keine abrechenbare Beitragsperiode ermittelt werden.');
        }

        $invoice = $this->createMembershipInvoiceForPeriod($member, $period, 'entwurf');

        if (!$invoice) {
            return redirect()
                ->route('invoices.show', $this->findMembershipInvoiceForPeriod($member, $period))
                ->with('success', 'Für diese Periode gibt es bereits eine Rechnung. Die bestehende Rechnung wurde geöffnet.');
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Die Beitragsrechnung wurde als Entwurf angelegt.');
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $tenant = auth()->user()->tenant;
        $submitAction = $request->input('submit_action', 'save');

        $validated = $this->validateInvoicePayload($request, $tenantId);

        if ($submitAction === 'send' && blank($validated['recipient_email'] ?? null) && ($validated['recipient_type'] ?? null) === 'free') {
            return back()
                ->withInput()
                ->withErrors(['recipient_email' => 'Bitte hinterlege eine E-Mail-Adresse, wenn das Dokument direkt versendet werden soll.']);
        }

        [$member, $contact, $recipientSnapshot] = $this->resolveRecipientSnapshot($validated, $tenantId);
        $texts = $this->resolveInvoiceTexts($validated, $tenant, $recipientSnapshot['recipient_salutation'] ?? null);

        $invoice = $this->createInvoiceWithUniqueNumber([
            'tenant_id'      => $tenantId,
            'document_type'  => $validated['document_type'],
            'member_id'      => $member?->id,
            'contact_id'     => $contact?->id,
            'income_account_id' => $validated['income_account_id'],
            'invoice_date'   => $validated['invoice_date'],
            'due_date'       => $validated['due_date'] ?? Carbon::parse($validated['invoice_date'])->copy()->addDays(14)->toDateString(),
            'discount'       => $validated['discount'] ?? 0,
            'tax_rate'       => $validated['tax_rate'] ?? 0,
            'status'         => $validated['status'],
            ...$recipientSnapshot,
            ...$texts,
        ]);

        $this->replaceInvoiceItems($invoice, $validated['items']);

        if ($submitAction === 'send') {
            if (blank($invoice->recipient_email)) {
                return redirect()
                    ->route('invoices.show', $invoice)
                    ->with('error', 'Das Dokument wurde gespeichert, aber es ist keine Empfaenger-E-Mail hinterlegt.');
            }

            return $this->sendInvoiceMessage($invoice, true);
        }

        return redirect()->route('invoices.show', $invoice);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        if (!$invoice->isDraft()) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Nur Entwuerfe koennen bearbeitet werden.');
        }

        $tenantId = auth()->user()->tenant_id;
        $tenant = auth()->user()->tenant;
        $submitAction = $request->input('submit_action', 'save');
        $validated = $this->validateInvoicePayload($request, $tenantId);

        if ($submitAction === 'send' && blank($validated['recipient_email'] ?? null) && ($validated['recipient_type'] ?? null) === 'free') {
            return back()
                ->withInput()
                ->withErrors(['recipient_email' => 'Bitte hinterlege eine E-Mail-Adresse, wenn das Dokument direkt versendet werden soll.']);
        }

        [$member, $contact, $recipientSnapshot] = $this->resolveRecipientSnapshot($validated, $tenantId);
        $texts = $this->resolveInvoiceTexts($validated, $tenant, $recipientSnapshot['recipient_salutation'] ?? null);

        $invoice->forceFill([
            'document_type'  => $validated['document_type'],
            'member_id'      => $member?->id,
            'contact_id'     => $contact?->id,
            'income_account_id' => $validated['income_account_id'],
            'invoice_date'   => $validated['invoice_date'],
            'due_date'       => $validated['due_date'] ?? Carbon::parse($validated['invoice_date'])->copy()->addDays(14)->toDateString(),
            'discount'       => $validated['discount'] ?? 0,
            'tax_rate'       => $validated['tax_rate'] ?? 0,
            'status'         => $validated['status'],
            ...$recipientSnapshot,
            ...$texts,
        ])->save();

        $this->replaceInvoiceItems($invoice, $validated['items']);

        if ($submitAction === 'send') {
            if (blank($invoice->recipient_email)) {
                return redirect()
                    ->route('invoices.show', $invoice)
                    ->with('error', 'Das Dokument wurde gespeichert, aber es ist keine Empfaenger-E-Mail hinterlegt.');
            }

            return $this->sendInvoiceMessage($invoice->fresh(), true);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', $invoice->getDocumentLabel() . ' wurde aktualisiert.');
    }

    public function generateMembershipInvoices(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $createdCount = 0;

        $validated = $request->validate([
            'membership_id' => ['nullable', Rule::exists('memberships', 'id')->where('tenant_id', $tenantId)],
        ]);

        $members = Member::where('tenant_id', $tenantId)
            ->when($validated['membership_id'] ?? null, fn ($query, $membershipId) => $query->where('membership_id', $membershipId))
            ->whereNull('archived_at')
            ->whereNull('family_payer_id')
            ->with(['membership', 'familyMembers'])
            ->get();

        foreach ($members as $member) {
            foreach ($this->getBillablePeriodsForMember($member) as $period) {
                if ($this->createMembershipInvoiceForPeriod($member, $period, 'entwurf')) {
                    $createdCount++;
                }
            }
        }

        $message = $createdCount === 1
            ? '1 Beitragsrechnung als Entwurf vorbereitet.'
            : $createdCount . ' Beitragsrechnungen als Entwurf vorbereitet.';

        if ($createdCount === 0) {
            $message = 'Keine neuen Beitragsrechnungen vorbereitet. Für die aktuellen Perioden bestehen vermutlich bereits Rechnungen oder es fehlen Beitragsdaten.';
        }

        return redirect()
            ->route('invoices.index')
            ->with('success', $message);
    }

    private function createInvoiceWithUniqueNumber(array $data): Invoice
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $data['invoice_number'] = $this->generateUniqueInvoiceNumber(
                $data['document_type'] ?? 'invoice',
                $attempt,
                $data['tenant_id'] ?? null
            );

            try {
                return Invoice::create($data);
            } catch (QueryException $e) {
                if (!$this->isDuplicateInvoiceNumberException($e)) {
                    throw $e;
                }
            }
        }

        abort(500, 'Es konnte keine eindeutige Rechnungsnummer erzeugt werden.');
    }

    private function invoiceNumberExists(string $invoiceNumber): bool
    {
        return Invoice::where('invoice_number', $invoiceNumber)->exists();
    }

    private function isDuplicateInvoiceNumberException(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'invoices_invoice_number_unique');
    }

    private function createMembershipInvoiceForPeriod(Member $member, array $period, string $status): ?Invoice
    {
        if ($this->findMembershipInvoiceForPeriod($member, $period)) {
            return null;
        }

        $amount = (float) ($member->membership_amount ?? $member->membership?->amount);
        $admissionFee = $this->resolveAdmissionFeeForMember($member);
        $interval = $this->normalizeMembershipInterval($member->membership_interval ?? $member->membership?->interval);
        $tenant = auth()->user()->tenant;
        $now = now();
        $membershipName = $member->membership?->name ?: 'Mitgliedschaft';

        return DB::transaction(function () use ($member, $period, $status, $now, $tenant, $membershipName, $interval, $amount, $admissionFee) {
            $familyMembers = $member->familyMembers()
                ->whereNull('archived_at')
                ->get();

            $invoice = $this->createInvoiceWithUniqueNumber([
                'tenant_id' => $member->tenant_id,
                'document_type' => 'invoice',
                'member_id' => $member->id,
                'income_account_id' => $this->resolveDefaultIncomeAccountId($member->tenant_id, 'membership'),
                'invoice_date' => $now,
                'due_date' => $now->copy()->addDays(14),
                'period_year' => $period['from']->year,
                'period_from' => $period['from'],
                'period_to' => $period['to'],
                'status' => $status,
                ...$this->snapshotFromMember($member),
                ...$this->resolveInvoiceTexts([], $tenant, $member->salutation),
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Mitgliedsbeitrag ' . $membershipName . ' ' . $period['label'],
                'details' => $familyMembers->isNotEmpty()
                    ? 'Familienabrechnung für: ' . $familyMembers
                        ->map(fn (Member $familyMember) => $familyMember->full_name ?: ($familyMember->organization ?: 'Mitglied #' . $familyMember->id))
                        ->implode(', ')
                    : null,
                'quantity' => 1,
                'unit' => $this->getIntervalLabel($interval),
                'unit_price' => $amount,
            ]);

            if ($admissionFee > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Aufnahmegebühr ' . $membershipName,
                    'details' => 'Einmalige Aufnahmegebühr beim Eintritt in den Verein.',
                    'quantity' => 1,
                    'unit' => 'einmalig',
                    'unit_price' => $admissionFee,
                ]);
            }

            $appliedCredit = $this->applyMemberCreditsToInvoice($invoice, $member, $amount + $admissionFee, $period['label']);

            if ($appliedCredit > 0 && $invoice->fresh('items')->getTotal() <= 0.00001) {
                $invoice->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                ])->save();
            }

            $member->forceFill([
                'next_membership_invoice_on' => $period['to']->copy()->addDay()->toDateString(),
            ])->save();

            return $invoice;
        });
    }

    private function applyMemberCreditsToInvoice(Invoice $invoice, Member $member, float $invoiceAmount, string $periodLabel): float
    {
        $available = round((float) $member->availableCredits()->sum('remaining_amount'), 2);
        $creditToApply = min($available, max(0, $invoiceAmount));

        if ($creditToApply <= 0) {
            return 0.0;
        }

        $remainingToApply = $creditToApply;
        $parts = [];

        $credits = MemberCredit::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('remaining_amount', '>', 0)
            ->orderBy('credited_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($credits as $credit) {
            if ($remainingToApply <= 0) {
                break;
            }

            $portion = min((float) $credit->remaining_amount, $remainingToApply);

            if ($portion <= 0) {
                continue;
            }

            MemberCreditApplication::create([
                'tenant_id' => $member->tenant_id,
                'member_credit_id' => $credit->id,
                'invoice_id' => $invoice->id,
                'amount' => $portion,
                'applied_at' => now(),
            ]);

            $credit->forceFill([
                'remaining_amount' => round((float) $credit->remaining_amount - $portion, 2),
            ])->save();

            $parts[] = $credit->description;
            $remainingToApply -= $portion;
        }

        $applied = round($creditToApply - $remainingToApply, 2);

        if ($applied > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Verrechnetes Guthaben',
                'details' => 'Automatisch verrechnet fuer ' . $periodLabel . (count($parts) ? ': ' . implode(', ', array_unique($parts)) : ''),
                'quantity' => 1,
                'unit' => 'Guthaben',
                'unit_price' => -1 * $applied,
            ]);
        }

        return $applied;
    }

    private function resolveAdmissionFeeForMember(Member $member): float
    {
        $fee = (float) ($member->membership?->admission_fee ?? 0);

        if ($fee <= 0 || $this->memberAlreadyHasAdmissionFeeInvoice($member)) {
            return 0.0;
        }

        return round($fee, 2);
    }

    private function memberAlreadyHasAdmissionFeeInvoice(Member $member): bool
    {
        return InvoiceItem::query()
            ->where('description', 'like', 'Aufnahmegebühr%')
            ->whereHas('invoice', function ($query) use ($member) {
                $query->where('tenant_id', $member->tenant_id)
                    ->where('member_id', $member->id)
                    ->where('document_type', 'invoice')
                    ->where('status', '!=', 'storniert');
            })
            ->exists();
    }

    private function findMembershipInvoiceForPeriod(Member $member, array $period): ?Invoice
    {
        return Invoice::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('document_type', 'invoice')
            ->where('member_id', $member->id)
            ->whereDate('period_from', $period['from'])
            ->whereDate('period_to', $period['to'])
            ->first();
    }

    private function getBillablePeriodsForMember(Member $member): array
    {
        if ($member->family_payer_id) {
            return [];
        }

        if (!$member->membership) {
            return [];
        }

        $amount = $member->membership_amount ?? $member->membership->amount;
        $interval = $this->normalizeMembershipInterval($member->membership_interval ?? $member->membership->interval);

        if (empty($amount) || !$interval) {
            return [];
        }

        if ($member->next_membership_invoice_on && $member->next_membership_invoice_on->isFuture()) {
            return [];
        }

        return array_values(array_filter(
            $this->getPeriodsForInterval($interval),
            fn (array $period) => $this->memberBelongsToPeriod($member, $period)
        ));
    }

    private function memberBelongsToPeriod(Member $member, array $period): bool
    {
        $entryDate = $member->entry_date?->startOfDay();
        $exitDate = $member->exit_date?->endOfDay();
        $today = now()->startOfDay();

        if ($entryDate && $entryDate->gt($today)) {
            return false;
        }

        if ($exitDate && $exitDate->lt($today)) {
            return false;
        }

        if ($entryDate && $entryDate->gt($period['to'])) {
            return false;
        }

        if ($exitDate && $exitDate->lt($period['from'])) {
            return false;
        }

        return !$member->is_archived;
    }

    private function normalizeMembershipInterval(?string $interval): ?string
    {
        return match (strtolower(trim((string) $interval))) {
            'monatlich', 'monat' => 'monatlich',
            'vierteljährlich', 'vierteljaehrlich', 'quartal', 'quartalsweise' => 'vierteljährlich',
            'halbjährlich', 'halbjaehrlich', 'halbjahr' => 'halbjährlich',
            'jährlich', 'jaehrlich', 'jahr' => 'jährlich',
            default => null,
        };
    }

    private function getIntervalLabel(?string $interval): string
    {
        return $interval ?: 'Beitrag';
    }

    private function getPeriodsForInterval($interval)
    {
        $interval = $this->normalizeMembershipInterval($interval);
        $year = now()->year;
        $periods = [];

        switch ($interval) {
            case 'monatlich':
                for ($m = 1; $m <= now()->month; $m++) {
                    $from = Carbon::create($year, $m, 1)->startOfMonth();
                    $to = (clone $from)->endOfMonth();

                    $periods[] = [
                        'from' => $from,
                        'to'   => $to,
                        'label'=> $from->format('m/Y'),
                    ];
                }
                break;

            case 'vierteljährlich':
                for ($q = 1; $q <= ceil(now()->month / 3); $q++) {
                    $from = Carbon::create($year, 1, 1)->startOfYear()->addQuarters($q - 1);
                    $to = (clone $from)->endOfQuarter();

                    $periods[] = [
                        'from' => $from,
                        'to'   => $to,
                        'label'=> 'Q' . $q . ' ' . $year,
                    ];
                }
                break;

            case 'halbjährlich':
                if (now()->month >= 1) {
                    $periods[] = [
                        'from' => Carbon::create($year, 1, 1),
                        'to'   => Carbon::create($year, 6, 30),
                        'label'=> 'H1 ' . $year,
                    ];
                }

                if (now()->month >= 7) {
                    $periods[] = [
                        'from' => Carbon::create($year, 7, 1),
                        'to'   => Carbon::create($year, 12, 31),
                        'label'=> 'H2 ' . $year,
                    ];
                }
                break;

            case 'jährlich':
            default:
                $periods[] = [
                    'from' => Carbon::create($year, 1, 1),
                    'to'   => Carbon::create($year, 12, 31),
                    'label'=> $year,
                ];
                break;
        }

        return $periods;
    }

    public function show(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        $invoice->load(['member', 'contact', 'items', 'payments', 'incomeAccount']);
        $dispatchLogs = TemplateDispatchLog::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('channel', 'mail')
            ->whereIn('action', ['invoice_sent', 'invoice_reminder_sent'])
            ->where('meta->invoice_id', $invoice->id)
            ->with('creator')
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id')
            ->get();

        $reminderLogs = $dispatchLogs
            ->where('action', 'invoice_reminder_sent')
            ->values();

        $reminderCount = $reminderLogs->count();
        $lastReminderLog = $reminderLogs->first();
        $nextReminderLabel = $this->reminderLabelForLevel($reminderCount + 1);

        return view('invoices.show', compact('invoice', 'dispatchLogs', 'reminderLogs', 'reminderCount', 'lastReminderLog', 'nextReminderLabel'));
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        $invoice->load(['member', 'contact', 'items', 'incomeAccount']);
        $tenant = $invoice->tenant()->firstOrFail();
        $pdfBinary = $this->invoicePdfService->render($invoice, $tenant);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $invoice->getDocumentLabel() . '_' . $invoice->invoice_number . '.pdf"',
        ]);
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['entwurf', 'open', 'storniert'])],
        ]);

        if ($invoice->status === 'paid') {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Bereits bezahlte Dokumente koennen nicht manuell umgestellt werden.');
        }

        if ($validated['status'] === 'storniert' && $invoice->payments()->exists()) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Zu dieser Rechnung gibt es bereits Zahlungseintraege. Bitte klaert diese zuerst, bevor die Rechnung storniert wird.');
        }

        if ($validated['status'] === 'storniert') {
            $this->cancelInvoice($invoice);
        } else {
            $invoice->status = $validated['status'];

            if ($validated['status'] !== 'paid') {
                $invoice->paid_at = null;
            }

            $invoice->save();
            $this->syncEventBookingPaymentStatus($invoice);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Der Dokumentstatus wurde aktualisiert.');
    }

    public function bulkCancel(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
        ]);

        $invoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $validated['invoice_ids'])
            ->with('payments')
            ->get();

        $cancelledCount = 0;
        $skippedCount = 0;

        foreach ($invoices as $invoice) {
            if (!$invoice->isInvoice() || $invoice->isPaid() || $invoice->payments->isNotEmpty() || $invoice->status === 'storniert') {
                $skippedCount++;
                continue;
            }

            $this->cancelInvoice($invoice);
            $cancelledCount++;
        }

        if ($cancelledCount === 0) {
            return redirect()
                ->route('invoices.index')
                ->with('error', 'Keine der markierten Rechnungen konnte storniert werden. Bereits bezahlte, schon stornierte oder bereits gebuchte Dokumente bleiben unberuehrt.');
        }

        $message = $cancelledCount === 1
            ? '1 Rechnung wurde storniert.'
            : $cancelledCount . ' Rechnungen wurden storniert.';

        if ($skippedCount > 0) {
            $message .= ' ' . $skippedCount . ' Dokument(e) wurden uebersprungen.';
        }

        return redirect()
            ->route('invoices.index')
            ->with('success', $message);
    }

    public function destroyDraft(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        if (! $this->invoiceCanBeDeletedAsDraft($invoice)) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Nur echte Entwürfe ohne Zahlungen können gelöscht werden. Freigegebene oder versendete Rechnungen müssen storniert werden.');
        }

        $this->deleteDraftInvoice($invoice);

        return redirect()
            ->route('invoices.index', ['status' => 'entwurf'])
            ->with('success', 'Der Entwurf wurde gelöscht. Du kannst die Beitragsrechnung jetzt neu vorbereiten.');
    }

    public function bulkDestroyDrafts(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
        ]);

        $invoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $validated['invoice_ids'])
            ->with('payments')
            ->get();

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($invoices as $invoice) {
            if (! $this->invoiceCanBeDeletedAsDraft($invoice)) {
                $skippedCount++;
                continue;
            }

            $this->deleteDraftInvoice($invoice);
            $deletedCount++;
        }

        if ($deletedCount === 0) {
            return redirect()
                ->route('invoices.index')
                ->with('error', 'Keine der markierten Rechnungen konnte gelöscht werden. Löschbar sind nur Entwürfe ohne Zahlungen.');
        }

        $message = $deletedCount === 1
            ? '1 Entwurf wurde gelöscht.'
            : $deletedCount . ' Entwürfe wurden gelöscht.';

        if ($skippedCount > 0) {
            $message .= ' ' . $skippedCount . ' Dokument(e) wurden übersprungen, weil sie keine löschbaren Entwürfe sind.';
        }

        return redirect()
            ->route('invoices.index', ['status' => 'entwurf'])
            ->with('success', $message);
    }

    public function sendMail(Invoice $invoice)
    {
        return $this->sendInvoiceMessage($invoice, false);
    }

    public function reminderPreview(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        $invoice->load(['member', 'contact', 'items', 'incomeAccount']);
        $tenant = $invoice->tenant()->firstOrFail();

        if ($response = $this->guardReminderEligibility($invoice)) {
            return $response;
        }

        $reminderCount = TemplateDispatchLog::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('channel', 'mail')
            ->where('action', 'invoice_reminder_sent')
            ->where('meta->invoice_id', $invoice->id)
            ->count();

        $reminderLevel = $reminderCount + 1;
        $subject = $this->buildReminderSubject($invoice, $reminderLevel);
        $body = $this->buildReminderMailBody($invoice, $tenant, $reminderLevel);
        $renderedBody = $this->renderPlainTextMailBody($body);

        return view('invoices.reminder-preview', compact('invoice', 'tenant', 'reminderCount', 'reminderLevel', 'subject', 'body', 'renderedBody'));
    }

    public function sendReminder(Invoice $invoice)
    {
        return $this->sendInvoiceReminder(request(), $invoice);
    }

    private function sendInvoiceMessage(Invoice $invoice, bool $fromCreateFlow)
    {
        $this->authorizeAccess($invoice);

        $invoice->load(['member', 'contact', 'items', 'incomeAccount']);
        $tenant = $invoice->tenant()->firstOrFail();

        if (blank($invoice->recipient_email)) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Fuer diese Rechnung ist keine Empfaenger-E-Mail hinterlegt.');
        }

        $this->tenantMailConfigurator->apply($tenant);

        $subject = $invoice->getDocumentLabel() . ' ' . $invoice->invoice_number;
        $body = $this->buildInvoiceMailBody($invoice, $tenant);
        $action = 'invoice_sent';

        return $this->deliverInvoiceMail(
            invoice: $invoice,
            tenant: $tenant,
            subject: $subject,
            body: $body,
            action: $action,
            successMessage: $invoice->getDocumentLabel() . ($fromCreateFlow ? ' wurde gespeichert und direkt per E-Mail versendet.' : ' wurde per E-Mail versendet.')
        );
    }

    private function sendInvoiceReminder(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        $invoice->load(['member', 'contact', 'items', 'incomeAccount']);
        $tenant = $invoice->tenant()->firstOrFail();

        if ($response = $this->guardReminderEligibility($invoice)) {
            return $response;
        }

        $this->tenantMailConfigurator->apply($tenant);

        $reminderCount = TemplateDispatchLog::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('channel', 'mail')
            ->where('action', 'invoice_reminder_sent')
            ->where('meta->invoice_id', $invoice->id)
            ->count();

        $reminderLevel = $reminderCount + 1;
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $subject = $validated['subject'];
        $body = $this->renderPlainTextMailBody($validated['body']);

        return $this->deliverInvoiceMail(
            invoice: $invoice,
            tenant: $tenant,
            subject: $subject,
            body: $body,
            action: 'invoice_reminder_sent',
            successMessage: $this->reminderLabelForLevel($reminderLevel) . ' wurde per E-Mail versendet.',
            meta: [
                'reminder_level' => $reminderLevel,
                'reminder_label' => $this->reminderLabelForLevel($reminderLevel),
                'overdue_days' => $invoice->overdueDays(),
            ],
            messageExcerpt: $this->reminderLabelForLevel($reminderLevel) . ' zu Rechnung ' . $invoice->invoice_number . ' wurde per Mail versendet.'
        );
    }

    private function guardReminderEligibility(Invoice $invoice)
    {
        if ($invoice->isOffer()) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Fuer Angebote gibt es kein Mahnwesen.');
        }

        if ($invoice->status !== 'open') {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Nur offene Rechnungen koennen gemahnt werden.');
        }

        if (! $invoice->isOverdue()) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Diese Rechnung ist noch nicht ueberfaellig.');
        }

        if (blank($invoice->recipient_email)) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Fuer diese Rechnung ist keine Empfaenger-E-Mail hinterlegt.');
        }

        return null;
    }

    private function deliverInvoiceMail(
        Invoice $invoice,
        $tenant,
        string $subject,
        string $body,
        string $action,
        string $successMessage,
        array $meta = [],
        ?string $messageExcerpt = null,
    ) {
        if (blank($invoice->recipient_email)) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Fuer diese Rechnung ist keine Empfaenger-E-Mail hinterlegt.');
        }

        $pdfBinary = $this->invoicePdfService->render($invoice, $tenant);
        $pdfName = $invoice->getDocumentLabel() . '_' . $invoice->invoice_number . '.pdf';

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;

        $dispatchLog = TemplateDispatchLog::create([
            'tenant_id' => $invoice->tenant_id,
            'template_id' => null,
            'created_by' => auth()->id(),
            'channel' => 'mail',
            'action' => $action,
            'recipient_type' => $invoice->recipient_type ?? 'free',
            'member_id' => $invoice->member_id,
            'contact_id' => $invoice->contact_id,
            'recipient_name' => $invoice->getRecipientDisplayName(),
            'recipient_reference' => $invoice->recipient_email,
            'subject' => $subject,
            'message_excerpt' => $messageExcerpt ?: $invoice->getDocumentLabel() . ' ' . $invoice->invoice_number . ' wurde per Mail versendet.',
            'dispatched_at' => now(),
            'meta' => array_merge([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'document_type' => $invoice->document_type,
            ], $meta),
        ]);

        $trackedBody = $this->mailTrackingService->instrument($body, $dispatchLog);

        try {
            Mail::send('mail.layout', [
                'body' => $trackedBody,
                'tenant' => $tenant,
            ], function ($mail) use ($invoice, $subject, $fromAddress, $fromName, $replyToAddress, $tenant, $pdfBinary, $pdfName) {
                $mail->to($invoice->recipient_email, $invoice->recipient_name ?: null)
                    ->subject($subject)
                    ->from($fromAddress, $fromName)
                    ->attachData($pdfBinary, $pdfName, ['mime' => 'application/pdf']);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?? $fromName);
                }
            });

            if ($invoice->status === 'entwurf' && $invoice->isInvoice()) {
                $invoice->forceFill(['status' => 'open'])->save();
            }
        } catch (\Throwable $e) {
            $dispatchLog->delete();

            Log::warning('Rechnungsversand fehlgeschlagen', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient_email' => $invoice->recipient_email,
                'action' => $action,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'Die Mail konnte nicht versendet werden. Bitte pruefe die Mailkonfiguration.');
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', $successMessage);
    }

    private function authorizeAccess(Invoice $invoice)
    {
        abort_if($invoice->tenant_id !== auth()->user()->tenant_id, 403);
    }

    private function invoiceCanBeDeletedAsDraft(Invoice $invoice): bool
    {
        return $invoice->isDraft()
            && ! $invoice->payments()->exists()
            && $invoice->status !== 'paid';
    }

    private function deleteDraftInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->loadMissing(['items', 'member']);

            $applications = MemberCreditApplication::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('invoice_id', $invoice->id)
                ->with('credit')
                ->lockForUpdate()
                ->get();

            foreach ($applications as $application) {
                if ($application->credit) {
                    $application->credit->forceFill([
                        'remaining_amount' => round((float) $application->credit->remaining_amount + (float) $application->amount, 2),
                    ])->save();
                }

                $application->delete();
            }

            $member = $invoice->member;

            $invoice->items()->delete();
            $invoice->delete();

            if ($member && $invoice->period_from && $invoice->period_to) {
                $this->resetMemberNextMembershipInvoiceDate($member);
            }
        });
    }

    private function resetMemberNextMembershipInvoiceDate(Member $member): void
    {
        $lastInvoice = Invoice::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('document_type', 'invoice')
            ->where('member_id', $member->id)
            ->where('status', '!=', 'storniert')
            ->whereNotNull('period_to')
            ->orderByDesc('period_to')
            ->first();

        $nextDate = $lastInvoice?->period_to
            ? $lastInvoice->period_to->copy()->addDay()->toDateString()
            : ($member->entry_date?->isFuture() ? $member->entry_date->toDateString() : now()->toDateString());

        $member->forceFill([
            'next_membership_invoice_on' => $nextDate,
        ])->save();
    }

    private function validateInvoicePayload(Request $request, int $tenantId): array
    {
        return $request->validate([
            'document_type'         => ['required', Rule::in(['invoice', 'offer'])],
            'recipient_type'        => ['required', Rule::in(['member', 'contact', 'free'])],
            'member_id'             => ['nullable', Rule::exists('members', 'id')->where('tenant_id', $tenantId)],
            'contact_id'            => ['nullable', Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)],
            'income_account_id'     => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'recipient_name'        => ['nullable', 'string', 'max:255'],
            'recipient_company'     => ['nullable', 'string', 'max:255'],
            'recipient_salutation'  => ['nullable', 'string', 'max:255'],
            'recipient_email'       => ['nullable', 'email', 'max:255'],
            'recipient_street'      => ['nullable', 'string', 'max:255'],
            'recipient_zip'         => ['nullable', 'string', 'max:50'],
            'recipient_city'        => ['nullable', 'string', 'max:255'],
            'recipient_country'     => ['nullable', 'string', 'max:255'],
            'invoice_date'          => 'required|date',
            'due_date'              => 'nullable|date|after_or_equal:invoice_date',
            'status'                => ['required', Rule::in(['entwurf', 'open'])],
            'items'                 => 'required|array|min:1',
            'items.*.description'   => 'required|string|max:255',
            'items.*.details'       => 'nullable|string',
            'items.*.quantity'      => 'required|numeric|min:0',
            'items.*.unit'          => 'nullable|string|max:50',
            'items.*.unit_price'    => 'required|numeric|min:0',
            'discount'              => 'nullable|numeric|min:0|max:100',
            'tax_rate'              => 'nullable|numeric|min:0|max:100',
            'intro_text'            => 'nullable|string',
            'payment_text'          => 'nullable|string',
            'closing_text'          => 'nullable|string',
        ]);
    }

    private function replaceInvoiceItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach ($items as $item) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $item['description'],
                'details'     => $item['details'] ?? null,
                'quantity'    => $item['quantity'],
                'unit'        => $item['unit'] ?? 'Stück',
                'unit_price'  => $item['unit_price'],
            ]);
        }
    }

    private function buildInvoiceMailBody(Invoice $invoice, $tenant): string
    {
        $documentLabel = $invoice->getDocumentLabel();
        $total = number_format($invoice->getTotal(), 2, ',', '.') . ' EUR';
        $dueDate = $invoice->due_date?->format('d.m.Y');

        $body = '<p>Guten Tag,</p>';
        $body .= '<p>anbei erhaltet ihr ' . ($invoice->isOffer() ? 'unser ' : 'die ') . '<strong>' . e($documentLabel) . ' ' . e($invoice->invoice_number) . '</strong>.</p>';

        if ($invoice->isInvoice()) {
            $body .= '<p>Der Gesamtbetrag betraegt <strong>' . e($total) . '</strong>';

            if ($dueDate) {
                $body .= ' und ist bis zum <strong>' . e($dueDate) . '</strong> faellig';
            }

            $body .= '.</p>';
        }

        $body .= '<p>Das Dokument findet ihr als PDF im Anhang.</p>';
        $body .= '<p>Viele Gruesse<br>' . e($tenant->name ?? 'Euer Verein') . '</p>';

        return $body;
    }

    private function buildReminderSubject(Invoice $invoice, int $reminderLevel): string
    {
        return $this->reminderLabelForLevel($reminderLevel) . ': Rechnung ' . $invoice->invoice_number;
    }

    private function buildReminderMailBody(Invoice $invoice, $tenant, int $reminderLevel): string
    {
        $total = number_format($invoice->getTotal(), 2, ',', '.') . ' EUR';
        $dueDate = $invoice->due_date?->format('d.m.Y');
        $overdueDays = $invoice->overdueDays();
        $label = $this->reminderLabelForLevel($reminderLevel);

        $body = "Guten Tag,\n\n";
        $body .= 'hiermit senden wir euch die ' . $label . ' zu unserer Rechnung ' . $invoice->invoice_number . ".\n\n";
        $body .= 'Der offene Betrag betraegt weiterhin ' . $total;

        if ($dueDate) {
            $body .= ' und war am ' . $dueDate . ' faellig';
        }

        if ($overdueDays > 0) {
            $body .= ' (aktuell ' . $overdueDays . ' Tag' . ($overdueDays === 1 ? '' : 'e') . ' ueberfaellig)';
        }

        $body .= ".\n\n";
        $body .= "Bitte prueft die Zahlung. Falls der Betrag bereits ueberwiesen wurde, betrachtet diese Nachricht bitte als gegenstandslos.\n\n";
        $body .= "Die Rechnung findet ihr noch einmal als PDF im Anhang.\n\n";
        $body .= 'Viele Gruesse' . "\n" . ($tenant->name ?? 'Euer Verein');

        return $body;
    }

    private function renderPlainTextMailBody(string $body): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($body));

        if ($normalized === '') {
            return '';
        }

        $paragraphs = preg_split("/\n{2,}/", $normalized) ?: [];

        $htmlParagraphs = array_map(function (string $paragraph): string {
            return '<p>' . nl2br(e(trim($paragraph)), false) . '</p>';
        }, array_filter($paragraphs, fn (string $paragraph) => trim($paragraph) !== ''));

        return implode('', $htmlParagraphs);
    }

    private function reminderLabelForLevel(int $level): string
    {
        return match (true) {
            $level <= 1 => 'Zahlungserinnerung',
            $level === 2 => '1. Mahnung',
            $level === 3 => '2. Mahnung',
            default => 'Letzte Mahnung',
        };
    }

    private function resolveRecipientSnapshot(array $validated, int $tenantId): array
    {
        $type = $validated['recipient_type'];
        $member = null;
        $contact = null;

        if ($type === 'member') {
            $member = Member::where('tenant_id', $tenantId)->findOrFail($validated['member_id']);
            $snapshot = $this->snapshotFromMember($member);
        } elseif ($type === 'contact') {
            $contact = Contact::where('tenant_id', $tenantId)->findOrFail($validated['contact_id']);
            $snapshot = $this->snapshotFromContact($contact);
        } else {
            if (blank($validated['recipient_name'] ?? null) || blank($validated['recipient_street'] ?? null) || blank($validated['recipient_zip'] ?? null) || blank($validated['recipient_city'] ?? null)) {
                abort(422, 'Bitte erfasse fuer freie Rechnungsadressen mindestens Name, Strasse, PLZ und Ort.');
            }

            $snapshot = [
                'recipient_type' => 'free',
                'recipient_name' => $validated['recipient_name'],
                'recipient_company' => $validated['recipient_company'] ?? null,
                'recipient_salutation' => $validated['recipient_salutation'] ?? null,
                'recipient_email' => $validated['recipient_email'] ?? null,
                'recipient_street' => $validated['recipient_street'],
                'recipient_zip' => $validated['recipient_zip'],
                'recipient_city' => $validated['recipient_city'],
                'recipient_country' => $validated['recipient_country'] ?? 'Deutschland',
            ];
        }

        return [$member, $contact, $snapshot];
    }

    private function snapshotFromMember(Member $member): array
    {
        return [
            'recipient_type' => 'member',
            'recipient_name' => $member->full_name,
            'recipient_company' => $member->organization,
            'recipient_salutation' => $member->salutation,
            'recipient_email' => $member->email,
            'recipient_street' => trim(($member->care_of ? $member->care_of . ' / ' : '') . $member->street . ($member->address_addition ? ' ' . $member->address_addition : '')),
            'recipient_zip' => $member->zip,
            'recipient_city' => $member->city,
            'recipient_country' => $member->country ?: 'Deutschland',
        ];
    }

    private function snapshotFromContact(Contact $contact): array
    {
        return [
            'recipient_type' => 'contact',
            'recipient_name' => $contact->full_name ?: $contact->display_name,
            'recipient_company' => $contact->organization ?: $contact->company,
            'recipient_salutation' => $contact->salutation,
            'recipient_email' => $contact->primary_email,
            'recipient_street' => trim(($contact->care_of ? $contact->care_of . ' / ' : '') . ($contact->street ?: '') . ($contact->address_addition ? ' ' . $contact->address_addition : '')),
            'recipient_zip' => $contact->zip ?: $contact->postal_code,
            'recipient_city' => $contact->city,
            'recipient_country' => $contact->country ?: 'Deutschland',
        ];
    }

    private function incomeAccountsForTenant(int $tenantId)
    {
        return Account::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'einnahme')
            ->where('active', true)
            ->orderBy('number')
            ->orderBy('name')
            ->get();
    }

    private function suggestIncomeAccount(string $documentType, $incomeAccounts): array
    {
        if ($incomeAccounts->isEmpty()) {
            return [null, 'Bitte legt zuerst mindestens ein aktives Einnahmekonto an.'];
        }

        if ($documentType === 'offer') {
            return [
                $this->findIncomeAccountByNames($incomeAccounts, ['Sonstige Erlöse', 'Sonstige Erloese', 'Dienstleistungen', 'Erlöse', 'Erloese']) ?? $incomeAccounts->first(),
                'Auch fuer Angebote lohnt sich ein passendes Ertragskonto, damit eine spaetere Rechnung fachlich sofort sauber aufgesetzt ist.',
            ];
        }

        return [
            $this->findIncomeAccountByNames($incomeAccounts, ['Sonstige Erlöse', 'Sonstige Erloese', 'Dienstleistungen', 'Erlöse', 'Erloese']) ?? $incomeAccounts->first(),
            'Bitte waehle hier das fachlich passende Ertragskonto. Dieses Konto wird spaeter automatisch fuer den Zahlungseingang verwendet.',
        ];
    }

    private function findIncomeAccountByNames($incomeAccounts, array $names): ?Account
    {
        foreach ($names as $name) {
            $account = $incomeAccounts->first(function (Account $account) use ($name) {
                return mb_strtolower((string) $account->name) === mb_strtolower($name);
            });

            if ($account) {
                return $account;
            }
        }

        return null;
    }

    private function resolveDefaultIncomeAccountId(int $tenantId, string $context = 'general'): ?int
    {
        $incomeAccounts = $this->incomeAccountsForTenant($tenantId);

        if ($incomeAccounts->isEmpty()) {
            return null;
        }

        $preferredNames = match ($context) {
            'membership' => ['Mitgliederbeiträge', 'Mitgliedsbeitraege'],
            'event' => ['Veranstaltungen', 'Veranstaltungserlöse', 'Veranstaltungserloese', 'Teilnahmegebühren', 'Teilnahmegebuehren', 'Event-Einnahmen'],
            default => ['Sonstige Erlöse', 'Sonstige Erloese', 'Dienstleistungen', 'Erlöse', 'Erloese'],
        };

        return ($this->findIncomeAccountByNames($incomeAccounts, $preferredNames) ?? $incomeAccounts->first())?->id;
    }

    private function resolveInvoiceTexts(array $validated, $tenant, ?string $salutation): array
    {
        $introText = trim((string) ($validated['intro_text'] ?? ''));
        $paymentText = trim((string) ($validated['payment_text'] ?? ''));
        $closingText = trim((string) ($validated['closing_text'] ?? ''));

        if ($introText === '') {
            $greeting = $salutation ?: 'Guten Tag';
            $documentType = $validated['document_type'] ?? 'invoice';
            $introText = $documentType === 'offer'
                ? $greeting . ",\n\nvielen Dank fuer eure Anfrage. Fuer die unten aufgefuehrten Leistungen unterbreiten wir euch folgendes Angebot."
                : $greeting . ",\n\nvielen Dank fuer die gute Zusammenarbeit. Fuer die unten aufgefuehrten Leistungen stellen wir euch folgende Rechnung.";
        }

        if ($paymentText === '') {
            $documentType = $validated['document_type'] ?? 'invoice';
            $paymentText = $documentType === 'offer'
                ? "Bei Rueckfragen oder wenn ihr das Angebot annehmen moechtet, meldet euch bitte bei uns. Nach Annahme koennen wir daraus direkt eine Rechnung ableiten."
                : "Bitte ueberweist den Rechnungsbetrag innerhalb der angegebenen Frist auf das unten genannte Vereinskonto.";
        }

        if ($closingText === '') {
            $closingText = "Mit freundlichen Gruessen\n" . ($tenant->name ?? 'Euer Verein');
        }

        return [
            'intro_text' => $introText,
            'payment_text' => $paymentText,
            'closing_text' => $closingText,
        ];
    }

    private function syncEventBookingPaymentStatus(Invoice $invoice): void
    {
        $paymentStatus = match ($invoice->status) {
            'paid' => 'paid',
            'storniert' => 'cancelled',
            default => 'open',
        };

        $invoice->eventBookings()->update([
            'payment_status' => $paymentStatus,
        ]);
    }

    private function cancelInvoice(Invoice $invoice): void
    {
        $this->invoiceCancellationService->cancel($invoice);
    }

    private function generateUniqueInvoiceNumber(string $documentType = 'invoice', int $attempt = 0, ?int $tenantId = null): string
    {
        $baseNumber = Invoice::generateDocumentNumber($documentType, $tenantId);

        if ($attempt === 0 && !$this->invoiceNumberExists($baseNumber)) {
            return $baseNumber;
        }

        do {
            $invoiceNumber = $baseNumber . '-' . random_int(1000, 9999);
        } while ($this->invoiceNumberExists($invoiceNumber));

        return $invoiceNumber;
    }
}
