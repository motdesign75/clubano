<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Document;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DonationController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $status = $request->input('status', 'all');
        $search = trim((string) $request->input('search'));

        $query = Donation::forCurrentTenant()
            ->with(['member', 'transaction'])
            ->whereYear('donated_at', $year)
            ->latest('donated_at')
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('certificate_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        $summaryBase = Donation::forCurrentTenant()->whereYear('donated_at', $year);
        $donations = $query->paginate(20)->withQueryString();

        $summary = [
            'total' => (clone $summaryBase)->where('status', '!=', Donation::STATUS_CANCELLED)->sum('amount'),
            'count' => (clone $summaryBase)->count(),
            'drafts' => (clone $summaryBase)->where('status', Donation::STATUS_DRAFT)->count(),
            'issued' => (clone $summaryBase)->whereIn('status', [Donation::STATUS_ISSUED, Donation::STATUS_SENT])->count(),
        ];

        return view('donations.index', compact('donations', 'summary', 'year', 'status', 'search') + [
            'readiness' => auth()->user()->tenant->loadMissing('donationFreistellungDocument')->donationCertificateReadiness(),
        ]);
    }

    public function create()
    {
        return view('donations.create', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validateDonation($request);
        $tenantId = auth()->user()->tenant_id;
        $member = null;

        if (!empty($validated['member_id'])) {
            $member = Member::forCurrentTenant()->findOrFail($validated['member_id']);
        }

        if ($member && ($validated['fill_from_member'] ?? false)) {
            $validated = array_merge($validated, $this->memberDonorPayload($member));
        }

        $createTransaction = (bool) ($validated['create_transaction'] ?? false);
        unset($validated['fill_from_member'], $validated['create_transaction'], $validated['cash_account_id'], $validated['income_account_id']);

        $donation = Donation::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'status' => Donation::STATUS_DRAFT,
            'kind' => 'money',
        ]));

        if ($createTransaction) {
            $transaction = $this->createTransactionFor($request, $donation);
            $donation->forceFill(['transaction_id' => $transaction->id])->save();
        }

        return redirect()->route('donations.show', $donation)
            ->with('success', 'Die Spende wurde erfasst. Du kannst jetzt die Zuwendungsbestätigung erstellen.');
    }

    public function show(Donation $donation)
    {
        $this->authorizeDonation($donation);

        return view('donations.show', [
            'donation' => $donation->load(['member', 'transaction']),
            'tenant' => auth()->user()->tenant,
        ]);
    }

    public function pdf(Donation $donation)
    {
        $this->authorizeDonation($donation);
        if (! auth()->user()->tenant->loadMissing('donationFreistellungDocument')->canIssueDonationCertificates()) {
            return redirect()->route('donations.show', $donation)
                ->with('error', 'Bitte hinterlege zuerst einen gültigen Freistellungsbescheid und die Pflichtangaben zur Gemeinnützigkeit.');
        }

        $donation->issueCertificate();

        $pdf = Pdf::loadView('donations.pdf', [
            'donation' => $donation->fresh(['tenant', 'member']),
            'tenant' => auth()->user()->tenant,
        ])->setPaper('a4');

        return $pdf->download('zuwendungsbestaetigung-' . $donation->certificate_number . '.pdf');
    }

    public function markSent(Donation $donation)
    {
        $this->authorizeDonation($donation);
        if (! auth()->user()->tenant->loadMissing('donationFreistellungDocument')->canIssueDonationCertificates()) {
            return back()->with('error', 'Die Zuwendungsbestätigung kann erst nach gültigem Gemeinnützigkeitsnachweis als versendet markiert werden.');
        }

        $donation->issueCertificate();
        $donation->forceFill([
            'status' => Donation::STATUS_SENT,
            'sent_at' => now(),
        ])->save();

        return back()->with('success', 'Die Zuwendungsbestätigung wurde als versendet markiert.');
    }

    public function cancel(Donation $donation)
    {
        $this->authorizeDonation($donation);
        $donation->forceFill(['status' => Donation::STATUS_CANCELLED])->save();

        return redirect()->route('donations.index')->with('success', 'Die Spende wurde storniert.');
    }

    public function settings()
    {
        $tenant = auth()->user()->tenant->loadMissing('donationFreistellungDocument');

        return view('donations.settings', [
            'tenant' => $tenant,
            'readiness' => $tenant->donationCertificateReadiness(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'donation_certificates_enabled' => ['nullable', 'boolean'],
            'donation_certificates_send_enabled' => ['nullable', 'boolean'],
            'donation_tax_office' => ['nullable', 'string', 'max:255'],
            'donation_tax_number' => ['nullable', 'string', 'max:255'],
            'donation_notice_authority' => ['nullable', 'string', 'max:255'],
            'donation_notice_date' => ['nullable', 'date'],
            'donation_notice_valid_until' => ['nullable', 'date'],
            'donation_purposes' => ['nullable', 'string', 'max:5000'],
            'donation_email_body' => ['nullable', 'string', 'max:5000'],
            'freistellung_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:51200'],
        ]);

        $tenant = auth()->user()->tenant;
        unset($validated['freistellung_document']);

        if ($request->hasFile('freistellung_document')) {
            $file = $request->file('freistellung_document');
            $path = $file->store('documents/' . $tenant->id . '/gemeinnuetzigkeit', 'local');

            $document = Document::create([
                'tenant_id' => $tenant->id,
                'uploaded_by' => auth()->id(),
                'title' => 'Freistellungsbescheid',
                'category' => Document::CATEGORY_CLUB,
                'status' => Document::STATUS_ACTIVE,
                'description' => 'Nachweis der Gemeinnützigkeit für Zuwendungsbestätigungen.',
                'tags' => ['Gemeinnützigkeit', 'Freistellungsbescheid', 'Spenden'],
                'document_date' => $validated['donation_notice_date'] ?? now()->toDateString(),
                'expires_at' => $validated['donation_notice_valid_until'] ?? (isset($validated['donation_notice_date']) ? \Carbon\Carbon::parse($validated['donation_notice_date'])->addYears(5)->toDateString() : null),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            $validated['donation_freistellung_document_id'] = $document->id;
        }

        $tenant->update(array_merge($validated, [
            'donation_certificates_enabled' => $request->boolean('donation_certificates_enabled'),
            'donation_certificates_send_enabled' => $request->boolean('donation_certificates_send_enabled'),
        ]));

        $tenant->refresh()->loadMissing('donationFreistellungDocument');
        $readiness = $tenant->donationCertificateReadiness();

        return redirect()->route('donations.settings')->with(
            $readiness['can_issue'] ? 'success' : 'warning',
            $readiness['can_issue']
                ? 'Die Einstellungen sind vollständig. Zuwendungsbestätigungen können erstellt werden.'
                : 'Die Einstellungen wurden gespeichert, aber Zuwendungsbestätigungen bleiben gesperrt, bis der Nachweis vollständig ist.'
        );
    }

    private function formData(): array
    {
        return [
            'members' => Member::forCurrentTenant()
                ->whereNull('archived_at')
                ->orderBy('organization')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'organization', 'email', 'street', 'zip', 'city']),
            'cashAccounts' => Account::forCurrentTenant()
                ->whereIn('type', ['bank', 'kasse'])
                ->where('active', true)
                ->orderBy('number')
                ->get(),
            'incomeAccounts' => Account::forCurrentTenant()
                ->where('type', 'einnahme')
                ->where('active', true)
                ->orderBy('number')
                ->get(),
        ];
    }

    private function validateDonation(Request $request): array
    {
        return $request->validate([
            'member_id' => ['nullable', 'integer'],
            'fill_from_member' => ['nullable', 'boolean'],
            'donated_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'donor_name' => ['required_without:member_id', 'nullable', 'string', 'max:255'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'donor_street' => ['nullable', 'string', 'max:255'],
            'donor_zip' => ['nullable', 'string', 'max:20'],
            'donor_city' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::in(['ueberweisung', 'bar', 'lastschrift', 'karte', 'sonstiges'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_transaction' => ['nullable', 'boolean'],
            'cash_account_id' => ['required_if:create_transaction,1', 'nullable', 'integer'],
            'income_account_id' => ['required_if:create_transaction,1', 'nullable', 'integer'],
        ]);
    }

    private function memberDonorPayload(Member $member): array
    {
        return [
            'donor_name' => $member->organization ?: ($member->full_name ?: 'Mitglied #' . $member->id),
            'donor_email' => $member->email,
            'donor_street' => $member->street,
            'donor_zip' => $member->zip,
            'donor_city' => $member->city,
        ];
    }

    private function createTransactionFor(Request $request, Donation $donation): Transaction
    {
        $cashAccount = Account::forCurrentTenant()
            ->whereIn('type', ['bank', 'kasse'])
            ->findOrFail($request->integer('cash_account_id'));

        $incomeAccount = Account::forCurrentTenant()
            ->where('type', 'einnahme')
            ->findOrFail($request->integer('income_account_id'));

        $transaction = Transaction::create([
            'tenant_id' => $donation->tenant_id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'date' => $donation->donated_at,
            'description' => 'Spende von ' . $donation->donor_name,
            'amount' => $donation->amount,
            'account_from_id' => $incomeAccount->id,
            'account_to_id' => $cashAccount->id,
            'tax_area' => 'ideell',
            'receipt_number' => $donation->certificate_number,
            'status' => 'abgeschlossen',
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
        ]);

        $cashAccount->updateBalance();
        $incomeAccount->updateBalance();

        return $transaction;
    }

    private function authorizeDonation(Donation $donation): void
    {
        if ((string) $donation->tenant_id !== (string) auth()->user()->tenant_id) {
            abort(404);
        }
    }
}
