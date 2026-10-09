<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\BankStatementImportService;
use App\Services\ReceiptRecognitionService;
use App\Services\ReceiptStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class BankImportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $status = $request->input('status', 'offen');
        $importId = $request->input('import');

        $bankAccounts = Account::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['bank', 'kasse'])
            ->where('active', true)
            ->orderBy('number')
            ->get();

        $postingAccounts = Account::query()
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->where('is_postable', true)
            ->orderBy('type')
            ->orderBy('number')
            ->get();

        $invoices = $this->invoiceChoices();
        $payableDocuments = $this->payableDocumentChoices();
        $manualBookingChoices = Transaction::query()
            ->with(['account_from', 'account_to'])
            ->where('tenant_id', $tenantId)
            ->latest('date')
            ->latest('id')
            ->limit(300)
            ->get()
            ->reject(fn (Transaction $transaction) => $transaction->isCancelled());

        $imports = BankImport::query()
            ->with('account')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(12)
            ->get();

        $transactionsQuery = BankTransaction::query()
            ->with(['account', 'selectedAccount', 'transaction', 'bankImport'])
            ->where('tenant_id', $tenantId)
            ->latest('booking_date')
            ->latest('id');

        if ($importId) {
            $transactionsQuery->where('bank_import_id', $importId);
        }

        if ($status === 'offen') {
            $transactionsQuery->whereIn('status', [BankTransaction::STATUS_PENDING, BankTransaction::STATUS_READY]);
        } elseif (in_array($status, [
            BankTransaction::STATUS_PENDING,
            BankTransaction::STATUS_READY,
            BankTransaction::STATUS_BOOKED,
            BankTransaction::STATUS_DUPLICATE,
            BankTransaction::STATUS_IGNORED,
        ], true)) {
            $transactionsQuery->where('status', $status);
        }

        $bankTransactions = $transactionsQuery->paginate(30)->withQueryString();

        $summary = [
            'pending' => BankTransaction::where('tenant_id', $tenantId)->where('status', BankTransaction::STATUS_PENDING)->count(),
            'ready' => BankTransaction::where('tenant_id', $tenantId)->where('status', BankTransaction::STATUS_READY)->count(),
            'booked' => BankTransaction::where('tenant_id', $tenantId)->where('status', BankTransaction::STATUS_BOOKED)->count(),
            'ignored' => BankTransaction::where('tenant_id', $tenantId)->where('status', BankTransaction::STATUS_IGNORED)->count(),
        ];
        $assistantStats = [
            'open_invoices' => $invoices->filter(fn (Invoice $invoice) => $invoice->getRemainingAmount() > 0.009)->count(),
            'open_payables' => $payableDocuments->filter(fn (Document $document) => $document->payableRemainingAmount() > 0.009)->count(),
            'suggested_receipts' => BankTransaction::where('tenant_id', $tenantId)
                ->whereIn('status', [BankTransaction::STATUS_PENDING, BankTransaction::STATUS_READY])
                ->whereIn('receipt_kind', ['system_invoice', 'document'])
                ->count(),
        ];

        return view('bank-imports.index', compact(
            'bankAccounts',
            'postingAccounts',
            'imports',
            'bankTransactions',
            'invoices',
            'payableDocuments',
            'summary',
            'assistantStats',
            'status',
            'importId',
            'manualBookingChoices'
        ));
    }

    public function store(Request $request, BankStatementImportService $service)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->whereIn('type', ['bank', 'kasse'])
                    ->where('active', true)),
            ],
            'statement_file' => ['required', 'file', 'max:12288'],
        ]);

        try {
            $parsed = $service->parse($request->file('statement_file'));
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if (empty($parsed['rows'])) {
            return back()->with('error', 'In der Datei wurden keine Bankumsätze gefunden.');
        }

        $bankImport = null;

        DB::transaction(function () use (&$bankImport, $parsed, $validated, $tenantId, $service, $request) {
            $rowCount = count($parsed['rows']);
            $imported = 0;
            $duplicates = 0;
            $autoAssigned = 0;
            $existingBookings = 0;
            $invoiceSuggestions = 0;
            $payableSuggestions = 0;
            $dates = collect($parsed['rows'])->pluck('booking_date')->filter()->sort()->values();
            $accountsByNumber = Account::query()
                ->where('tenant_id', $tenantId)
                ->where('active', true)
                ->where('is_postable', true)
                ->get()
                ->keyBy(fn (Account $account) => trim((string) $account->number));

            $bankImport = BankImport::create([
                'tenant_id' => $tenantId,
                'account_id' => $validated['account_id'],
                'uploaded_by' => auth()->id(),
                'filename' => $request->file('statement_file')->getClientOriginalName(),
                'format' => $parsed['format'],
                'status' => 'review',
                'row_count' => $rowCount,
                'statement_from' => $dates->first(),
                'statement_to' => $dates->last(),
            ]);

            foreach ($parsed['rows'] as $row) {
                $useSourceAccountFromFile = $parsed['format'] === 'TRINKWERT';
                $sourceAccount = $useSourceAccountFromFile
                    ? $accountsByNumber->get((string) ($row['source_account_number'] ?? ''))
                    : null;
                $selectedAccount = $accountsByNumber->get((string) ($row['selected_account_number'] ?? ''));
                $sourceAccountId = (int) ($sourceAccount?->id ?? $validated['account_id']);
                $selectedAccountId = $selectedAccount && (int) $selectedAccount->id !== $sourceAccountId
                    ? (int) $selectedAccount->id
                    : null;

                $fingerprint = $service->fingerprint($tenantId, $sourceAccountId, $row);
                $existingBooking = $this->matchingImportedTransaction($tenantId, $sourceAccountId, $row, $fingerprint);

                if ($existingBooking) {
                    $selectedAccountId = $this->selectedAccountIdFromExistingBooking($existingBooking, $sourceAccountId, $row)
                        ?: $selectedAccountId;
                }

                $suggestion = $existingBooking
                    ? null
                    : $this->receiptSuggestionForBankRow($tenantId, $row);

                if ($suggestion && ! $selectedAccountId && ! empty($suggestion['selected_account_id'])) {
                    $selectedAccountId = (int) $suggestion['selected_account_id'];
                }

                if (BankTransaction::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('fingerprint', $fingerprint)
                    ->exists()) {
                    $duplicates++;
                    continue;
                }

                BankTransaction::create([
                    'tenant_id' => $tenantId,
                    'bank_import_id' => $bankImport->id,
                    'account_id' => $sourceAccountId,
                    'selected_account_id' => $selectedAccountId,
                    'booking_date' => $row['booking_date'],
                    'value_date' => $row['value_date'],
                    'amount' => $row['amount'],
                    'currency' => $row['currency'],
                    'direction' => $row['direction'],
                    'counterparty_name' => $row['counterparty_name'],
                    'counterparty_iban' => $row['counterparty_iban'],
                    'purpose' => $row['purpose'],
                    'end_to_end_id' => $row['end_to_end_id'],
                    'bank_reference' => $row['bank_reference'],
                    'fingerprint' => $fingerprint,
                    'transaction_id' => $existingBooking?->id,
                    'receipt_kind' => $suggestion['receipt_kind'] ?? null,
                    'receipt_meta' => $suggestion['receipt_meta'] ?? null,
                    'status' => $existingBooking
                        ? BankTransaction::STATUS_BOOKED
                        : ($selectedAccountId ? BankTransaction::STATUS_READY : BankTransaction::STATUS_PENDING),
                    'raw_data' => array_filter([
                        ...($row['raw'] ?? []),
                        'clubano_suggestion' => $suggestion['summary'] ?? null,
                    ]),
                ]);

                $imported++;
                if ($existingBooking) {
                    $existingBookings++;
                }
                if (($suggestion['receipt_kind'] ?? null) === 'system_invoice') {
                    $invoiceSuggestions++;
                } elseif (($suggestion['receipt_kind'] ?? null) === 'document') {
                    $payableSuggestions++;
                }
                if ($selectedAccountId) {
                    $autoAssigned++;
                }
            }

            $bankImport->update([
                'imported_count' => $imported,
                'duplicate_count' => $duplicates,
                'meta' => array_filter([
                    'auto_assigned_count' => $autoAssigned,
                    'existing_booking_count' => $existingBookings,
                    'invoice_suggestion_count' => $invoiceSuggestions,
                    'payable_suggestion_count' => $payableSuggestions,
                    'source' => $parsed['format'] === 'TRINKWERT' ? 'Trinkwert' : null,
                ]),
                'booked_count' => $existingBookings,
            ]);
        });

        $autoAssigned = (int) ($bankImport?->meta['auto_assigned_count'] ?? 0);
        $existingBookings = (int) ($bankImport?->meta['existing_booking_count'] ?? 0);
        $invoiceSuggestions = (int) ($bankImport?->meta['invoice_suggestion_count'] ?? 0);
        $payableSuggestions = (int) ($bankImport?->meta['payable_suggestion_count'] ?? 0);
        $message = "{$bankImport->imported_count} Umsätze importiert, {$bankImport->duplicate_count} Dubletten übersprungen.";

        if ($autoAssigned > 0) {
            $message .= " {$autoAssigned} Zuordnung(en) wurden aus der Datei vorgeschlagen.";
        }

        if ($invoiceSuggestions > 0) {
            $message .= " {$invoiceSuggestions} Clubano-Rechnung(en) wurden erkannt.";
        }

        if ($payableSuggestions > 0) {
            $message .= " {$payableSuggestions} Eingangsrechnung(en) wurden erkannt.";
        }

        if ($existingBookings > 0) {
            $message .= " {$existingBookings} bereits vorhandene Buchung(en) wurden wieder verknüpft.";
        }

        return redirect()
            ->route('bank-imports.index', ['import' => $bankImport?->id])
            ->with('success', $message);
    }

    public function destroy(BankImport $bankImport)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_unless((int) $bankImport->tenant_id === $tenantId, 404);

        DB::transaction(function () use ($bankImport) {
            $bankImport->load('bankTransactions');

            foreach ($bankImport->bankTransactions as $bankTransaction) {
                if (! $bankTransaction->transaction_id) {
                    app(ReceiptStorage::class)->delete($bankTransaction->receipt_file);
                }
            }

            $bankImport->delete();
        });

        return redirect()
            ->route('bank-imports.index')
            ->with('success', 'Import wurde gelöscht. Bereits erzeugte Buchungen bleiben erhalten und werden bei einem Neuimport erkannt.');
    }

    public function update(Request $request, BankTransaction $bankTransaction, ReceiptRecognitionService $receiptRecognitionService)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->abortIfForeignTenant($bankTransaction, $tenantId);

        $validated = $request->validate([
            'selected_account_id' => [
                'required',
                'different:source_account_id',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->where('is_postable', true)),
            ],
            'source_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->where('is_postable', true)),
            ],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:12288'],
            'receipt_kind' => ['nullable', Rule::in(['none', 'upload', 'vertrag', 'system_invoice', 'document'])],
            'invoice_id' => [
                Rule::requiredIf(fn () => $request->input('receipt_kind') === 'system_invoice'),
                'nullable',
                Rule::exists('invoices', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('document_type', 'invoice')
                    ->whereNotIn('status', ['entwurf', 'storniert'])),
            ],
            'payable_document_id' => [
                Rule::requiredIf(fn () => $request->input('receipt_kind') === 'document'),
                'nullable',
                Rule::exists('documents', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_booking_receipt', true)
                    ->whereNull('archived_at')),
            ],
            'contract_reference' => [
                Rule::requiredIf(fn () => $request->input('receipt_kind') === 'vertrag' && ! $request->hasFile('receipt_file')),
                'nullable',
                'string',
                'max:255',
            ],
            'contract_location' => ['nullable', 'string', 'max:255'],
            'contract_date' => ['nullable', 'date'],
        ]);

        $receiptData = $this->receiptData($request, $validated, $bankTransaction, $receiptRecognitionService);
        $paymentReviewMessage = null;

        DB::transaction(function () use ($bankTransaction, $validated, $receiptData, $tenantId, &$paymentReviewMessage) {
            $fingerprint = app(BankStatementImportService::class)->fingerprint(
                $tenantId,
                (int) $validated['source_account_id'],
                $this->rowFromBankTransaction($bankTransaction)
            );

            if ($fingerprint !== $bankTransaction->fingerprint) {
                $duplicateExists = BankTransaction::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('fingerprint', $fingerprint)
                    ->whereKeyNot($bankTransaction->id)
                    ->exists();

                abort_if($duplicateExists, 422, 'Für dieses Konto existiert bereits ein gleicher Bankumsatz.');
            }

            $bankTransaction->update([
                'account_id' => $validated['source_account_id'],
                'selected_account_id' => $validated['selected_account_id'],
                'fingerprint' => $fingerprint,
                'status' => $bankTransaction->transaction_id
                    ? BankTransaction::STATUS_BOOKED
                    : BankTransaction::STATUS_READY,
                ...$receiptData,
            ]);

            $this->syncLinkedTransaction($bankTransaction);

            $paymentReviewMessage = $this->paymentReviewMessage($bankTransaction->fresh());
        });

        return $this->backToBankTransaction($bankTransaction)
            ->with('success', $paymentReviewMessage ?: ($bankTransaction->transaction_id
                ? 'Konten wurden gespeichert und der Buchungsentwurf aktualisiert.'
                : 'Konten wurden gespeichert.'));
    }

    public function book(Request $request, BankTransaction $bankTransaction)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->abortIfForeignTenant($bankTransaction, $tenantId);

        if ($bankTransaction->status === BankTransaction::STATUS_BOOKED) {
            return back()->with('error', 'Dieser Bankumsatz wurde bereits gebucht.');
        }

        if ($request->filled('selected_account_id')) {
            $validated = $request->validate([
                'selected_account_id' => [
                    'required',
                    'integer',
                    'different:source_account_id',
                    Rule::exists('accounts', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('active', true)
                        ->where('is_postable', true)),
                ],
                'source_account_id' => [
                    'required',
                    'integer',
                    Rule::exists('accounts', 'id')->where(fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('active', true)
                        ->where('is_postable', true)),
                ],
            ]);

            if ((int) $validated['source_account_id'] !== (int) $bankTransaction->account_id) {
                return $this->backToBankTransaction($bankTransaction)
                    ->with('error', 'Das Bankkonto passt nicht mehr zu diesem Umsatz. Bitte lade die Seite neu.');
            }

            $bankTransaction->update([
                'selected_account_id' => (int) $validated['selected_account_id'],
                'status' => BankTransaction::STATUS_READY,
            ]);
        }

        if (! $bankTransaction->selected_account_id) {
            return back()->with('error', 'Bitte zuerst ein Gegenkonto auswählen.');
        }

        $transaction = DB::transaction(fn () => $this->createTransactionFromBankTransaction($bankTransaction));

        return $this->backToBankTransaction($bankTransaction)
            ->with('success', 'Buchungsentwurf ' . $transaction->receipt_number . ' wurde erstellt.');
    }

    public function bulkBook(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'bank_transaction_ids' => ['required', 'array', 'min:1'],
            'bank_transaction_ids.*' => ['integer'],
        ]);

        $bankTransactions = BankTransaction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $validated['bank_transaction_ids'])
            ->where('status', BankTransaction::STATUS_READY)
            ->whereNotNull('selected_account_id')
            ->get();

        $created = 0;

        DB::transaction(function () use ($bankTransactions, &$created) {
            foreach ($bankTransactions as $bankTransaction) {
                if ($bankTransaction->transaction_id) {
                    continue;
                }

                $this->createTransactionFromBankTransaction($bankTransaction);
                $created++;
            }
        });

        return back()->with('success', "{$created} Buchungsentwürfe wurden erstellt.");
    }

    public function ignore(BankTransaction $bankTransaction)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->abortIfForeignTenant($bankTransaction, $tenantId);

        if ($bankTransaction->status === BankTransaction::STATUS_BOOKED) {
            return back()->with('error', 'Gebuchte Bankumsätze können nicht ignoriert werden.');
        }

        $bankTransaction->update(['status' => BankTransaction::STATUS_IGNORED]);

        return $this->backToBankTransaction($bankTransaction)
            ->with('success', 'Bankumsatz wurde ausgeblendet.');
    }

    public function linkManualBooking(Request $request, BankTransaction $bankTransaction)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->abortIfForeignTenant($bankTransaction, $tenantId);

        if ($bankTransaction->status === BankTransaction::STATUS_BOOKED) {
            return $this->backToBankTransaction($bankTransaction)
                ->with('error', 'Dieser Bankumsatz wurde bereits gebucht.');
        }

        $validated = $request->validate([
            'transaction_id' => [
                'required',
                Rule::exists('transactions', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        $transaction = Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['account_from', 'account_to'])
            ->findOrFail($validated['transaction_id']);

        if ($transaction->isCancelled()) {
            return $this->backToBankTransaction($bankTransaction)
                ->with('error', 'Stornobuchungen können nicht als manuelle Buchung verknüpft werden.');
        }

        if (round(abs((float) $transaction->amount), 2) !== round(abs((float) $bankTransaction->amount), 2)) {
            return $this->backToBankTransaction($bankTransaction)
                ->with('error', 'Die ausgewählte Buchung hat nicht denselben Betrag.');
        }

        $sourceAccountId = (int) $bankTransaction->account_id;
        $selectedAccountId = $bankTransaction->isCredit()
            ? ((int) $transaction->account_to_id === $sourceAccountId ? (int) $transaction->account_from_id : null)
            : ((int) $transaction->account_from_id === $sourceAccountId ? (int) $transaction->account_to_id : null);

        if (! $selectedAccountId || $selectedAccountId === $sourceAccountId) {
            return $this->backToBankTransaction($bankTransaction)
                ->with('error', 'Die ausgewählte Buchung passt nicht zu diesem Bankkonto.');
        }

        DB::transaction(function () use ($bankTransaction, $transaction, $selectedAccountId) {
            $previousStatus = $bankTransaction->status;

            $bankTransaction->update([
                'transaction_id' => $transaction->id,
                'selected_account_id' => $selectedAccountId,
                'status' => BankTransaction::STATUS_BOOKED,
            ]);

            if ($previousStatus !== BankTransaction::STATUS_BOOKED) {
                $bankTransaction->bankImport?->increment('booked_count');
            }
        });

        return $this->backToBankTransaction($bankTransaction)
            ->with('success', 'Bankumsatz wurde mit der vorhandenen manuellen Buchung verknüpft.');
    }

    private function createTransactionFromBankTransaction(BankTransaction $bankTransaction): Transaction
    {
        $bankTransaction->refresh();
        $bankAccount = $bankTransaction->account;
        $contraAccount = $bankTransaction->selectedAccount;

        if (! $bankAccount || ! $contraAccount) {
            abort(422, 'Bankkonto oder Gegenkonto fehlt.');
        }

        $amount = abs((float) $bankTransaction->amount);
        $invoice = $this->invoiceFromBankTransaction($bankTransaction);

        [$accountFromId, $accountToId] = $this->accountPairForBankTransaction($bankTransaction);

        $transaction = Transaction::create([
            'tenant_id' => $bankTransaction->tenant_id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'date' => $bankTransaction->booking_date,
            'description' => $this->description($bankTransaction),
            'amount' => $amount,
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
            'tax_area' => $contraAccount->tax_area ?: $bankAccount->tax_area ?: 'ideell',
            'receipt_number' => 'BANK-' . $bankTransaction->booking_date?->format('Ymd') . '-' . str_pad((string) $bankTransaction->id, 6, '0', STR_PAD_LEFT),
            'receipt_kind' => $invoice ? 'system_invoice' : 'bank_import',
            'receipt_file' => $bankTransaction->receipt_file,
            'invoice_id' => $invoice?->id,
            'receipt_meta' => array_filter([
                'source' => 'Bankumsatz-Import',
                'bank_import_id' => $bankTransaction->bank_import_id,
                'bank_transaction_id' => $bankTransaction->id,
                'counterparty_name' => $bankTransaction->counterparty_name,
                'counterparty_iban' => $bankTransaction->counterparty_iban,
                'bank_reference' => $bankTransaction->bank_reference,
                'end_to_end_id' => $bankTransaction->end_to_end_id,
                'bank_import_fingerprint' => $bankTransaction->fingerprint,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                'linked_at' => $invoice ? now()->toIso8601String() : null,
                'linked_by' => $invoice ? auth()->id() : null,
            ]),
            'status' => 'entwurf',
        ]);

        if ($bankTransaction->receipt_kind === 'document') {
            $transaction->forceFill([
                'receipt_kind' => 'document',
                'receipt_meta' => array_filter([
                    ...($transaction->receipt_meta ?? []),
                    ...($bankTransaction->receipt_meta ?? []),
                ]),
            ])->save();

            $this->syncPayableDocumentFromBankTransaction($bankTransaction, $transaction);
        } elseif ($bankTransaction->receipt_kind === 'vertrag') {
            $transaction->forceFill([
                'receipt_kind' => 'vertrag',
                'receipt_meta' => array_filter([
                    ...($transaction->receipt_meta ?? []),
                    ...($bankTransaction->receipt_meta ?? []),
                ]),
            ])->save();
        } elseif ($bankTransaction->receipt_file) {
            $transaction->forceFill([
                'receipt_kind' => 'upload',
            ])->save();
        }

        $bankTransaction->update([
            'transaction_id' => $transaction->id,
            'status' => BankTransaction::STATUS_BOOKED,
        ]);

        $bankAccount->updateBalance();
        $contraAccount->updateBalance();
        $bankTransaction->bankImport?->increment('booked_count');

        return $transaction;
    }

    private function syncLinkedTransaction(BankTransaction $bankTransaction): void
    {
        $bankTransaction->refresh()->loadMissing(['account', 'selectedAccount', 'transaction']);

        if (! $bankTransaction->transaction) {
            return;
        }

        if ($bankTransaction->transaction->isFinalized()) {
            abort(422, 'Die verknüpfte Buchung ist bereits abgeschlossen und kann nicht mehr automatisch korrigiert werden.');
        }

        [$accountFromId, $accountToId] = $this->accountPairForBankTransaction($bankTransaction);
        $affectedAccountIds = collect([
            $bankTransaction->transaction->account_from_id,
            $bankTransaction->transaction->account_to_id,
            $accountFromId,
            $accountToId,
        ])->filter()->unique()->values();

        $invoice = $this->invoiceFromBankTransaction($bankTransaction);
        $receiptKind = $invoice ? 'system_invoice' : ($bankTransaction->receipt_kind ?: 'bank_import');
        $previousDocumentId = $bankTransaction->transaction->receipt_meta['document_id'] ?? null;
        $receiptMeta = array_filter([
            ...($bankTransaction->receipt_meta ?? []),
            'source' => 'Bankumsatz-Import',
            'bank_import_id' => $bankTransaction->bank_import_id,
            'bank_transaction_id' => $bankTransaction->id,
            'counterparty_name' => $bankTransaction->counterparty_name,
            'counterparty_iban' => $bankTransaction->counterparty_iban,
            'bank_reference' => $bankTransaction->bank_reference,
            'end_to_end_id' => $bankTransaction->end_to_end_id,
            'bank_import_fingerprint' => $bankTransaction->fingerprint,
            'invoice_id' => $invoice?->id,
            'invoice_number' => $invoice?->invoice_number,
            'linked_at' => $invoice ? now()->toIso8601String() : null,
            'linked_by' => $invoice ? auth()->id() : null,
        ]);

        $bankTransaction->transaction->forceFill([
            'date' => $bankTransaction->booking_date,
            'description' => $this->description($bankTransaction),
            'amount' => abs((float) $bankTransaction->amount),
            'account_from_id' => $accountFromId,
            'account_to_id' => $accountToId,
            'tax_area' => $bankTransaction->selectedAccount?->tax_area
                ?: $bankTransaction->account?->tax_area
                ?: 'ideell',
            'receipt_file' => $bankTransaction->receipt_file,
            'receipt_kind' => $receiptKind,
            'receipt_meta' => $receiptMeta,
            'invoice_id' => $invoice?->id,
            'updated_by' => auth()->id(),
        ])->save();

        $newDocumentId = $receiptMeta['document_id'] ?? null;
        if ($previousDocumentId && (string) $previousDocumentId !== (string) $newDocumentId) {
            $this->releasePayableDocument((int) $previousDocumentId, $bankTransaction->transaction->id);
        }

        if ($bankTransaction->receipt_kind === 'document') {
            $this->syncPayableDocumentFromBankTransaction($bankTransaction, $bankTransaction->transaction);
        }

        Account::query()
            ->whereIn('id', $affectedAccountIds)
            ->get()
            ->each(fn (Account $account) => $account->updateBalance());
    }

    private function accountPairForBankTransaction(BankTransaction $bankTransaction): array
    {
        $bankAccount = $bankTransaction->account;
        $contraAccount = $bankTransaction->selectedAccount;

        if (! $bankAccount || ! $contraAccount) {
            abort(422, 'Bankkonto oder Gegenkonto fehlt.');
        }

        return $bankTransaction->isCredit()
            ? [$contraAccount->id, $bankAccount->id]
            : [$bankAccount->id, $contraAccount->id];
    }

    private function description(BankTransaction $bankTransaction): string
    {
        return $this->descriptionFromParts($bankTransaction->counterparty_name, $bankTransaction->purpose);
    }

    private function descriptionFromRow(array $row): string
    {
        return $this->descriptionFromParts($row['counterparty_name'] ?? null, $row['purpose'] ?? null);
    }

    private function descriptionFromParts(?string $counterpartyName, ?string $purpose): string
    {
        $parts = array_filter([
            $counterpartyName,
            $purpose,
        ]);

        return mb_substr(implode(' - ', $parts) ?: 'Bankumsatz importiert', 0, 255);
    }

    private function receiptSuggestionForBankRow(int $tenantId, array $row): ?array
    {
        $amount = round(abs((float) ($row['amount'] ?? 0)), 2);

        if ($amount <= 0) {
            return null;
        }

        return ($row['direction'] ?? null) === 'credit'
            ? $this->invoiceSuggestionForBankRow($tenantId, $row, $amount)
            : $this->payableSuggestionForBankRow($tenantId, $row, $amount);
    }

    private function invoiceSuggestionForBankRow(int $tenantId, array $row, float $amount): ?array
    {
        $haystack = $this->compactSearchText($row);

        $invoice = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->whereNotIn('status', ['entwurf', 'storniert', 'paid'])
            ->with(['items', 'payments'])
            ->latest('invoice_date')
            ->latest('id')
            ->limit(250)
            ->get()
            ->first(function (Invoice $invoice) use ($amount, $haystack, $row) {
                $remaining = $invoice->getRemainingAmount();

                if (abs($remaining - $amount) >= 0.01) {
                    return false;
                }

                $invoiceNeedle = $this->compactReference($invoice->invoice_number);
                if ($invoiceNeedle !== '' && str_contains($haystack, $invoiceNeedle)) {
                    return true;
                }

                $recipientNeedle = $this->compactReference($invoice->recipient_name ?: $invoice->recipient_company);
                $counterparty = $this->compactReference($row['counterparty_name'] ?? null);

                return $recipientNeedle !== ''
                    && $counterparty !== ''
                    && (str_contains($counterparty, $recipientNeedle) || str_contains($recipientNeedle, $counterparty));
            });

        if (! $invoice) {
            return null;
        }

        return [
            'receipt_kind' => 'system_invoice',
            'selected_account_id' => $invoice->income_account_id,
            'receipt_meta' => array_filter([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_recipient' => $invoice->recipient_name,
                'invoice_total' => $invoice->getTotal(),
                'invoice_remaining_amount' => $invoice->getRemainingAmount(),
                'linked_at' => now()->toIso8601String(),
                'linked_by' => auth()->id(),
                'suggested_by' => 'bank_import_assistant',
            ]),
            'summary' => 'Clubano-Rechnung ' . $invoice->invoice_number,
        ];
    }

    private function payableSuggestionForBankRow(int $tenantId, array $row, float $amount): ?array
    {
        $haystack = $this->compactSearchText($row);

        $document = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->notArchived()
            ->where(function ($query) {
                $query->whereNull('linked_transaction_id')
                    ->orWhereIn('payable_status', [Document::PAYABLE_REVIEW, Document::PAYABLE_OPEN, Document::PAYABLE_PARTIAL]);
            })
            ->latest('updated_at')
            ->limit(250)
            ->get()
            ->first(function (Document $document) use ($amount, $haystack, $row) {
                if (abs($document->payableRemainingAmount() - $amount) >= 0.01) {
                    return false;
                }

                foreach ([$document->recognized_invoice_number, $document->payable_reference, $document->original_name] as $reference) {
                    $needle = $this->compactReference($reference);

                    if ($needle !== '' && str_contains($haystack, $needle)) {
                        return true;
                    }
                }

                $vendorNeedle = $this->compactReference($document->recognized_vendor);
                $counterparty = $this->compactReference($row['counterparty_name'] ?? null);

                return $vendorNeedle !== ''
                    && $counterparty !== ''
                    && (str_contains($counterparty, $vendorNeedle) || str_contains($vendorNeedle, $counterparty));
            });

        if (! $document) {
            return null;
        }

        return [
            'receipt_kind' => 'document',
            'receipt_meta' => array_filter([
                'document_id' => $document->id,
                'document_title' => $document->title,
                'document_name' => $document->original_name,
                'recognized_amount' => filled($document->recognized_amount) ? round((float) $document->recognized_amount, 2) : null,
                'recognized_currency' => $document->recognized_currency ?: 'EUR',
                'recognized_date' => $document->recognized_date?->toDateString(),
                'recognized_vendor' => $document->recognized_vendor,
                'recognized_invoice_number' => $document->recognized_invoice_number,
                'payable_due_date' => $document->payable_due_date?->toDateString(),
                'payable_reference' => $document->payable_reference,
                'linked_at' => now()->toIso8601String(),
                'linked_by' => auth()->id(),
                'suggested_by' => 'bank_import_assistant',
            ]),
            'summary' => 'Eingangsrechnung ' . ($document->recognized_invoice_number ?: $document->title),
        ];
    }

    private function compactSearchText(array $row): string
    {
        return $this->compactReference(implode(' ', array_filter([
            $row['counterparty_name'] ?? null,
            $row['counterparty_iban'] ?? null,
            $row['purpose'] ?? null,
            $row['end_to_end_id'] ?? null,
            $row['bank_reference'] ?? null,
        ])));
    }

    private function compactReference(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $value))) ?: '';
    }

    private function matchingImportedTransaction(int $tenantId, int $sourceAccountId, array $row, string $fingerprint): ?Transaction
    {
        $amount = abs((float) $row['amount']);
        $bookingDate = $row['booking_date'];
        $isCredit = ($row['direction'] ?? null) === 'credit';
        $description = $this->descriptionFromRow($row);
        $bankReference = Str::lower(trim((string) ($row['bank_reference'] ?? '')));
        $endToEndId = Str::lower(trim((string) ($row['end_to_end_id'] ?? '')));

        return Transaction::withoutGlobalScopes()
            ->with(['account_from', 'account_to'])
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $bookingDate)
            ->where('amount', $amount)
            ->where($isCredit ? 'account_to_id' : 'account_from_id', $sourceAccountId)
            ->get()
            ->first(function (Transaction $transaction) use ($fingerprint, $description, $bankReference, $endToEndId) {
                if ($transaction->isCancelled()) {
                    return false;
                }

                $meta = $transaction->receipt_meta ?? [];
                $source = (string) ($meta['source'] ?? '');
                $isBankImportBooking = $source === 'Bankumsatz-Import'
                    || str_starts_with((string) $transaction->receipt_number, 'BANK-');
                $isMoneyTransfer = $this->isMoneyTransfer($transaction);
                $isInvoicePayment = $transaction->hasSystemReceipt()
                    || $transaction->payment()->exists();

                if (! $isBankImportBooking && ! $isMoneyTransfer && ! $isInvoicePayment) {
                    return false;
                }

                if ($isMoneyTransfer && ! $isBankImportBooking) {
                    return true;
                }

                if ($isInvoicePayment && $this->bankDescriptionMentionsInvoice($transaction, $description)) {
                    return true;
                }

                if (($meta['bank_import_fingerprint'] ?? null) === $fingerprint) {
                    return true;
                }

                if ($bankReference !== '' && Str::lower((string) ($meta['bank_reference'] ?? '')) === $bankReference) {
                    return true;
                }

                if ($endToEndId !== '' && Str::lower((string) ($meta['end_to_end_id'] ?? '')) === $endToEndId) {
                    return true;
                }

                return (string) $transaction->description === $description;
            });
    }

    private function bankDescriptionMentionsInvoice(Transaction $transaction, string $bankDescription): bool
    {
        $transaction->loadMissing('invoice');

        $invoiceNumber = $transaction->invoice?->invoice_number
            ?: ($transaction->receipt_meta['invoice_number'] ?? null);

        if (blank($invoiceNumber)) {
            return false;
        }

        $haystack = Str::lower($bankDescription . ' ' . $transaction->description);
        $needle = Str::lower((string) $invoiceNumber);

        return str_contains($haystack, $needle);
    }

    private function isMoneyTransfer(Transaction $transaction): bool
    {
        $moneyAccountTypes = ['bank', 'kasse'];

        return in_array((string) $transaction->account_from?->type, $moneyAccountTypes, true)
            && in_array((string) $transaction->account_to?->type, $moneyAccountTypes, true);
    }

    private function rowFromBankTransaction(BankTransaction $bankTransaction): array
    {
        return [
            'booking_date' => $bankTransaction->booking_date?->toDateString(),
            'amount' => (float) $bankTransaction->amount,
            'currency' => $bankTransaction->currency,
            'counterparty_iban' => $bankTransaction->counterparty_iban,
            'counterparty_name' => $bankTransaction->counterparty_name,
            'purpose' => $bankTransaction->purpose,
            'end_to_end_id' => $bankTransaction->end_to_end_id,
            'bank_reference' => $bankTransaction->bank_reference,
        ];
    }

    private function selectedAccountIdFromExistingBooking(Transaction $transaction, int $sourceAccountId, array $row): ?int
    {
        $selectedAccountId = ($row['direction'] ?? null) === 'credit'
            ? $transaction->account_from_id
            : $transaction->account_to_id;

        return (int) $selectedAccountId !== $sourceAccountId
            ? (int) $selectedAccountId
            : null;
    }

    private function abortIfForeignTenant(BankTransaction $bankTransaction, int $tenantId): void
    {
        abort_unless((int) $bankTransaction->tenant_id === $tenantId, 404);
    }

    private function backToBankTransaction(BankTransaction $bankTransaction)
    {
        $previous = url()->previous();
        $withoutFragment = explode('#', $previous, 2)[0];

        return redirect()->to($withoutFragment . '#bank-transaction-' . $bankTransaction->id);
    }

    private function receiptData(Request $request, array $validated, BankTransaction $bankTransaction, ReceiptRecognitionService $receiptRecognitionService): array
    {
        $receiptKind = $validated['receipt_kind'] ?? 'none';

        if ($request->hasFile('receipt_file') && $receiptKind === 'none') {
            $receiptKind = 'upload';
        }
        $data = [
            'receipt_kind' => null,
            'receipt_meta' => null,
        ];

        if ($receiptKind === 'none') {
            app(ReceiptStorage::class)->delete($bankTransaction->receipt_file);

            return [
                ...$data,
                'receipt_file' => null,
            ];
        }

        if ($receiptKind === 'system_invoice') {
            app(ReceiptStorage::class)->delete($bankTransaction->receipt_file);

            $invoice = Invoice::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('document_type', 'invoice')
                ->whereNotIn('status', ['entwurf', 'storniert'])
                ->whereKey($validated['invoice_id'])
                ->firstOrFail();

            return [
                'receipt_file' => null,
                'receipt_kind' => 'system_invoice',
                'receipt_meta' => array_filter([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_recipient' => $invoice->recipient_name,
                    'invoice_total' => $invoice->getTotal(),
                    'invoice_remaining_amount' => $invoice->getRemainingAmount(),
                    'linked_at' => now()->toIso8601String(),
                    'linked_by' => auth()->id(),
                ]),
            ];
        }

        if ($receiptKind === 'document') {
            app(ReceiptStorage::class)->delete($bankTransaction->receipt_file);

            $document = Document::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('is_booking_receipt', true)
                ->notArchived()
                ->whereKey($validated['payable_document_id'])
                ->firstOrFail();

            if (
                $document->linked_transaction_id
                && (! $bankTransaction->transaction_id || (int) $document->linked_transaction_id !== (int) $bankTransaction->transaction_id)
            ) {
                abort(422, 'Diese Eingangsrechnung ist bereits mit einer anderen Buchung verknüpft.');
            }

            return [
                'receipt_file' => null,
                'receipt_kind' => 'document',
                'receipt_meta' => array_filter([
                    'document_id' => $document->id,
                    'document_title' => $document->title,
                    'document_name' => $document->original_name,
                    'recognized_amount' => filled($document->recognized_amount) ? round((float) $document->recognized_amount, 2) : null,
                    'recognized_currency' => $document->recognized_currency ?: 'EUR',
                    'recognized_date' => $document->recognized_date?->toDateString(),
                    'recognized_vendor' => $document->recognized_vendor,
                    'recognized_invoice_number' => $document->recognized_invoice_number,
                    'payable_due_date' => $document->payable_due_date?->toDateString(),
                    'payable_reference' => $document->payable_reference,
                    'linked_at' => now()->toIso8601String(),
                    'linked_by' => auth()->id(),
                ]),
            ];
        }

        $receiptFile = $bankTransaction->receipt_file;
        $recognition = [];

        if ($request->hasFile('receipt_file')) {
            app(ReceiptStorage::class)->delete($receiptFile);

            $recognition = $receiptRecognitionService->fromUpload($request->file('receipt_file'));
            $receiptFile = app(ReceiptStorage::class)->storeUploaded($request->file('receipt_file'), auth()->user()->tenant_id, 'bank-imports');
        }

        if ($receiptKind === 'vertrag') {
            return [
                'receipt_file' => $receiptFile,
                'receipt_kind' => 'vertrag',
                'receipt_meta' => [
                    'contract_document_id' => null,
                    'contract_document_title' => null,
                    'contract_reference' => trim((string) ($validated['contract_reference'] ?? '')),
                    'contract_location' => blank($validated['contract_location'] ?? null)
                        ? ($receiptFile ? 'Bankimport-Beleg' : null)
                        : trim((string) $validated['contract_location']),
                    'contract_date' => blank($validated['contract_date'] ?? null) ? null : $validated['contract_date'],
                    'marked_at' => now()->toIso8601String(),
                    'marked_by' => auth()->id(),
                ],
            ];
        }

        return [
            'receipt_file' => $receiptFile,
            'receipt_kind' => $receiptFile ? 'upload' : null,
            'receipt_meta' => $receiptFile ? $this->receiptRecognitionMeta($recognition ?: ($bankTransaction->receipt_meta ?? [])) : null,
        ];
    }

    private function receiptRecognitionMeta(array $recognition): array
    {
        return array_filter([
            'recognized_amount' => filled($recognition['recognized_amount'] ?? null)
                ? round((float) $recognition['recognized_amount'], 2)
                : null,
            'recognized_currency' => $recognition['recognized_currency'] ?? null,
            'recognized_date' => $recognition['recognized_date'] ?? null,
            'recognized_vendor' => $recognition['recognized_vendor'] ?? null,
            'recognized_invoice_number' => $recognition['recognized_invoice_number'] ?? null,
            'recognition_source' => $recognition['recognition_source'] ?? null,
            'recognition_notes' => $recognition['recognition_notes'] ?? null,
            'recognized_at' => filled($recognition['recognized_amount'] ?? null) ? now()->toIso8601String() : null,
        ]);
    }

    private function syncPayableDocumentFromBankTransaction(BankTransaction $bankTransaction, Transaction $transaction): void
    {
        $documentId = $bankTransaction->receipt_meta['document_id'] ?? null;

        if (! $documentId) {
            return;
        }

        $document = Document::query()
            ->where('tenant_id', $bankTransaction->tenant_id)
            ->where('is_booking_receipt', true)
            ->whereKey($documentId)
            ->first();

        if (! $document) {
            return;
        }

        $paidAmount = round(abs((float) $bankTransaction->amount), 2);
        $recognizedAmount = filled($document->recognized_amount) ? round((float) $document->recognized_amount, 2) : 0.0;
        $payableStatus = $recognizedAmount > 0 && $paidAmount + 0.009 >= $recognizedAmount
            ? Document::PAYABLE_PAID
            : Document::PAYABLE_PARTIAL;

        $document->update([
            'receipt_status' => Document::RECEIPT_BOOKED,
            'payable_status' => $payableStatus,
            'payable_paid_amount' => $paidAmount,
            'linked_transaction_id' => $transaction->id,
        ]);
    }

    private function releasePayableDocument(int $documentId, int $transactionId): void
    {
        $document = Document::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereKey($documentId)
            ->where('linked_transaction_id', $transactionId)
            ->first();

        if (! $document) {
            return;
        }

        $document->update([
            'receipt_status' => Document::RECEIPT_READY,
            'payable_status' => filled($document->recognized_amount)
                ? Document::PAYABLE_OPEN
                : Document::PAYABLE_REVIEW,
            'payable_paid_amount' => null,
            'linked_transaction_id' => null,
        ]);
    }

    private function paymentReviewMessage(BankTransaction $bankTransaction): ?string
    {
        $amount = round(abs((float) $bankTransaction->amount), 2);
        $meta = $bankTransaction->receipt_meta ?? [];
        $parts = [];

        $recognizedAmount = filled($meta['recognized_amount'] ?? null)
            ? round((float) $meta['recognized_amount'], 2)
            : null;

        if ($recognizedAmount !== null) {
            $label = $bankTransaction->receipt_kind === 'document' ? 'Eingangsrechnung geprüft' : 'Beleg geprüft';

            $parts[] = abs($recognizedAmount - $amount) < 0.01
                ? $label . ': Der erkannte Endbetrag passt zum Bankumsatz. Die Rechnung ist mit diesem Umsatz vollständig bezahlt.'
                : $label . ': Der erkannte Endbetrag weicht um ' . number_format(abs($recognizedAmount - $amount), 2, ',', '.') . ' € vom Bankumsatz ab. Bitte Rechnung und Zahlung prüfen.';
        }

        if ($bankTransaction->receipt_kind === 'system_invoice') {
            $invoice = $this->invoiceFromBankTransaction($bankTransaction);

            if ($invoice) {
                $remaining = round($invoice->getRemainingAmount(), 2);

                $parts[] = abs($remaining - $amount) < 0.01
                    ? 'Clubano-Rechnung geprüft: Der Bankumsatz gleicht den offenen Endbetrag vollständig aus.'
                    : 'Clubano-Rechnung geprüft: Offener Endbetrag ' . number_format($remaining, 2, ',', '.') . ' €, Bankumsatz ' . number_format($amount, 2, ',', '.') . ' €. Bitte Differenz prüfen.';
            }
        }

        return $parts ? implode(' ', $parts) : null;
    }

    private function invoiceChoices()
    {
        return Invoice::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('document_type', 'invoice')
            ->whereNotIn('status', ['entwurf', 'storniert'])
            ->withSum('payments as paid_amount', 'amount')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    private function payableDocumentChoices()
    {
        return Document::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('is_booking_receipt', true)
            ->notArchived()
            ->where(function ($query) {
                $query->whereNull('linked_transaction_id')
                    ->orWhereIn('payable_status', [Document::PAYABLE_REVIEW, Document::PAYABLE_OPEN, Document::PAYABLE_PARTIAL]);
            })
            ->orderByRaw('payable_due_date is null')
            ->orderBy('payable_due_date')
            ->latest('updated_at')
            ->limit(200)
            ->get();
    }

    private function invoiceFromBankTransaction(BankTransaction $bankTransaction): ?Invoice
    {
        if ($bankTransaction->receipt_kind !== 'system_invoice') {
            return null;
        }

        $invoiceId = $bankTransaction->receipt_meta['invoice_id'] ?? null;
        if (! $invoiceId) {
            return null;
        }

        return Invoice::query()
            ->where('tenant_id', $bankTransaction->tenant_id)
            ->whereKey($invoiceId)
            ->first();
    }
}
