<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankImport;
use App\Models\BankTransaction;
use App\Models\Transaction;
use App\Services\BankStatementImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BankImportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $status = $request->input('status', 'offen');
        $importId = $request->input('import');

        $bankAccounts = Account::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'bank')
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

        $imports = BankImport::query()
            ->with('account')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(12)
            ->get();

        $transactionsQuery = BankTransaction::query()
            ->with(['account', 'selectedAccount', 'transaction'])
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

        return view('bank-imports.index', compact(
            'bankAccounts',
            'postingAccounts',
            'imports',
            'bankTransactions',
            'summary',
            'status',
            'importId'
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
                    ->where('type', 'bank')
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
            $dates = collect($parsed['rows'])->pluck('booking_date')->filter()->sort()->values();

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
                $fingerprint = $service->fingerprint($tenantId, (int) $validated['account_id'], $row);

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
                    'account_id' => $validated['account_id'],
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
                    'status' => BankTransaction::STATUS_PENDING,
                    'raw_data' => $row['raw'],
                ]);

                $imported++;
            }

            $bankImport->update([
                'imported_count' => $imported,
                'duplicate_count' => $duplicates,
            ]);
        });

        return redirect()
            ->route('bank-imports.index', ['import' => $bankImport?->id])
            ->with('success', "{$bankImport->imported_count} Umsätze importiert, {$bankImport->duplicate_count} Dubletten übersprungen.");
    }

    public function update(Request $request, BankTransaction $bankTransaction)
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
            'source_account_id' => ['required', 'integer', Rule::in([(int) $bankTransaction->account_id])],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'receipt_kind' => ['nullable', Rule::in(['none', 'upload', 'vertrag'])],
            'contract_reference' => [
                Rule::requiredIf(fn () => $request->input('receipt_kind') === 'vertrag' && ! $request->hasFile('receipt_file')),
                'nullable',
                'string',
                'max:255',
            ],
            'contract_location' => ['nullable', 'string', 'max:255'],
            'contract_date' => ['nullable', 'date'],
        ]);

        if ($bankTransaction->status === BankTransaction::STATUS_BOOKED) {
            return back()->with('error', 'Dieser Bankumsatz wurde bereits gebucht.');
        }

        $receiptData = $this->receiptData($request, $validated, $bankTransaction);

        $bankTransaction->update([
            'selected_account_id' => $validated['selected_account_id'],
            'status' => BankTransaction::STATUS_READY,
            ...$receiptData,
        ]);

        return $this->backToBankTransaction($bankTransaction)
            ->with('success', 'Gegenkonto wurde gespeichert.');
    }

    public function book(BankTransaction $bankTransaction)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->abortIfForeignTenant($bankTransaction, $tenantId);

        if ($bankTransaction->status === BankTransaction::STATUS_BOOKED) {
            return back()->with('error', 'Dieser Bankumsatz wurde bereits gebucht.');
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

    private function createTransactionFromBankTransaction(BankTransaction $bankTransaction): Transaction
    {
        $bankTransaction->refresh();
        $bankAccount = $bankTransaction->account;
        $contraAccount = $bankTransaction->selectedAccount;

        if (! $bankAccount || ! $contraAccount) {
            abort(422, 'Bankkonto oder Gegenkonto fehlt.');
        }

        $amount = abs((float) $bankTransaction->amount);
        $isCredit = $bankTransaction->isCredit();

        $transaction = Transaction::create([
            'tenant_id' => $bankTransaction->tenant_id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'date' => $bankTransaction->booking_date,
            'description' => $this->description($bankTransaction),
            'amount' => $amount,
            'account_from_id' => $isCredit ? $contraAccount->id : $bankAccount->id,
            'account_to_id' => $isCredit ? $bankAccount->id : $contraAccount->id,
            'tax_area' => $contraAccount->tax_area ?: $bankAccount->tax_area ?: 'ideell',
            'receipt_number' => 'BANK-' . $bankTransaction->booking_date?->format('Ymd') . '-' . str_pad((string) $bankTransaction->id, 6, '0', STR_PAD_LEFT),
            'receipt_kind' => 'bank_import',
            'receipt_file' => $bankTransaction->receipt_file,
            'receipt_meta' => array_filter([
                'source' => 'Bankumsatz-Import',
                'bank_import_id' => $bankTransaction->bank_import_id,
                'bank_transaction_id' => $bankTransaction->id,
                'counterparty_name' => $bankTransaction->counterparty_name,
                'counterparty_iban' => $bankTransaction->counterparty_iban,
                'bank_reference' => $bankTransaction->bank_reference,
                'end_to_end_id' => $bankTransaction->end_to_end_id,
            ]),
            'status' => 'entwurf',
        ]);

        if ($bankTransaction->receipt_kind === 'vertrag') {
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

    private function description(BankTransaction $bankTransaction): string
    {
        $parts = array_filter([
            $bankTransaction->counterparty_name,
            $bankTransaction->purpose,
        ]);

        return mb_substr(implode(' - ', $parts) ?: 'Bankumsatz importiert', 0, 255);
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

    private function receiptData(Request $request, array $validated, BankTransaction $bankTransaction): array
    {
        $receiptKind = $validated['receipt_kind'] ?? 'none';
        $data = [
            'receipt_kind' => null,
            'receipt_meta' => null,
        ];

        if ($receiptKind === 'none') {
            if ($bankTransaction->receipt_file && Storage::disk('public')->exists($bankTransaction->receipt_file)) {
                Storage::disk('public')->delete($bankTransaction->receipt_file);
            }

            return [
                ...$data,
                'receipt_file' => null,
            ];
        }

        $receiptFile = $bankTransaction->receipt_file;

        if ($request->hasFile('receipt_file')) {
            if ($receiptFile && Storage::disk('public')->exists($receiptFile)) {
                Storage::disk('public')->delete($receiptFile);
            }

            $receiptFile = $request->file('receipt_file')->store(
                'receipts/' . auth()->user()->tenant_id . '/bank-imports',
                'public'
            );
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
            'receipt_meta' => null,
        ];
    }
}
