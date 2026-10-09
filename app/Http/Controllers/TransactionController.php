<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\BudgetCategory;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Tenant;
use App\Services\ReceiptStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function cashbook(Request $request)
    {
        return view('transactions.cashbook', $this->buildCashbookData($request, true));
    }

    public function cashbookPrint(Request $request)
    {
        return view('transactions.cashbook-print', $this->buildCashbookData($request, false));
    }

    public function cashbookPdf(Request $request)
    {
        $data = $this->buildCashbookData($request, false);

        $pdf = Pdf::loadView('transactions.cashbook-print', $data)->setPaper('a4', 'landscape');
        $accountLabel = $data['selectedCashAccount']?->number ?: 'kasse';

        return $pdf->download('Kassenbuch_' . $accountLabel . '_' . $data['selectedYear'] . ($data['selectedMonth'] ? '_' . str_pad((string) $data['selectedMonth'], 2, '0', STR_PAD_LEFT) : '') . '.pdf');
    }

    public function index(Request $request)
    {
        $filter = $request->input('filter');
        $year = $request->input('year');
        $month = $request->input('month');
        $search = trim((string) $request->input('search'));

        $transactions = Transaction::forCurrentTenant()
            ->with(['account_from.budgetCategory', 'account_to.budgetCategory', 'budgetCategory', 'creator', 'updater', 'finalizer', 'invoice'])
            ->orderByDesc('date');

        if ($filter === 'income') {
            $transactions->whereHas('account_from', fn($q) => $q->where('type', 'einnahme'));
        }

        if ($filter === 'expense') {
            $transactions->whereHas('account_to', fn($q) => $q->where('type', 'ausgabe'));
        }

        if ($filter === 'storno') {
            $transactions->where(function ($query) {
                $query->where('description', 'like', 'Storno:%')
                    ->orWhere('description', 'like', 'Storno zu %');
            });
        }

        if ($filter === 'missing_receipt') {
            $transactions->where(function ($query) {
                $query->whereNull('receipt_file')
                    ->orWhere('receipt_file', '');
            })->whereNull('invoice_id')
            ->where(function ($query) {
                $query->whereNull('receipt_kind')
                    ->orWhereNotIn('receipt_kind', ['vertrag', 'system_invoice']);
            })->where(function ($query) {
                $query->whereNull('receipt_kind')
                    ->orWhere('receipt_kind', '!=', 'vertrag');
            });
        }

        if ($year) {
            $transactions->whereYear('date', $year);
        }

        if ($month) {
            $transactions->whereMonth('date', $month);
        }

        if ($search !== '') {
            $transactions->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('account_from', fn ($accountQuery) => $accountQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('account_to', fn ($accountQuery) => $accountQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $summaryTransactions = (clone $transactions)->get();

        $transactions = $transactions
            ->paginate(20)
            ->withQueryString()
            ->through(function ($transaction) {
                $receiptStorage = app(ReceiptStorage::class);
                $exists = $transaction->receipt_file
                    ? $receiptStorage->exists($transaction->receipt_file)
                    : false;

                $transaction->receipt_exists = $exists;
                $transaction->system_receipt_exists = $transaction->hasSystemReceipt();
                $transaction->has_any_receipt = $transaction->hasAnyReceipt();
                $transaction->receipt_url = $exists
                    ? route('receipts.show', $transaction->receipt_file)
                    : null;

                return $transaction;
            });

        $summary = [
            'income_total' => $summaryTransactions
                ->filter(fn ($transaction) => in_array(optional($transaction->account_to)->type, ['bank', 'kasse']))
                ->sum('amount'),
            'expense_total' => $summaryTransactions
                ->filter(fn ($transaction) => in_array(optional($transaction->account_from)->type, ['bank', 'kasse']))
                ->sum('amount'),
            'receipt_count' => $summaryTransactions->filter(fn ($transaction) => $transaction->hasAnyReceipt())->count(),
            'missing_receipt_count' => $summaryTransactions->filter(fn ($transaction) => !$transaction->hasAnyReceipt())->count(),
            'filtered_count' => $summaryTransactions->count(),
        ];

        $contractDocuments = $this->contractDocumentChoices();

        return view('transactions.index', compact('transactions', 'filter', 'year', 'month', 'search', 'summary', 'contractDocuments'));
    }

    public function importDatev(Request $request)
    {
        $validated = $request->validate([
            'datev_file' => ['required', 'file', 'mimes:csv,txt', 'max:8192'],
            'status' => ['required', Rule::in(['entwurf', 'abgeschlossen'])],
        ]);

        $path = $request->file('datev_file')->getRealPath();
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return back()->with('error', 'Der Buchungsstapel konnte nicht gelesen werden.');
        }

        $meta = fgetcsv($handle, 0, ';');
        $header = fgetcsv($handle, 0, ';');

        $format = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) ($meta[0] ?? '')) ?? '', "\" \t\n\r\0\x0B");

        if (! $meta || ! $header || $format !== 'EXTF') {
            fclose($handle);

            return back()->with('error', 'Bitte lade einen DATEV-EXTF-Buchungsstapel hoch.');
        }

        $header = array_map(fn ($value) => $this->normalizeDatevHeader((string) $value), $header);
        $required = ['umsatz', 'sollhabenkennzeichen', 'konto', 'gegenkonto', 'belegdatum', 'buchungstext'];

        if (count(array_intersect($required, $header)) !== count($required)) {
            fclose($handle);

            return back()->with('error', 'Der Buchungsstapel enthält nicht alle benötigten DATEV-Spalten.');
        }

        $tenantId = auth()->user()->tenant_id;
        $sourceStamp = preg_replace('/\D+/', '', (string) ($meta[5] ?? '')) ?: now()->format('YmdHis');
        $fiscalYear = $this->datevFiscalYear($meta);
        $imported = 0;
        $skipped = 0;
        $createdAccounts = 0;
        $rowNumber = 2;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;
            $data = $this->mapDatevRow($header, $row);
            $receiptNumber = 'DATEV-' . $sourceStamp . '-' . str_pad((string) $rowNumber, 5, '0', STR_PAD_LEFT);

            if (Transaction::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('receipt_number', $receiptNumber)->exists()) {
                $skipped++;
                continue;
            }

            $amount = $this->parseDatevAmount((string) ($data['umsatz'] ?? ''));
            $debitCredit = Str::of((string) ($data['sollhabenkennzeichen'] ?? ''))->upper()->trim()->toString();
            $accountNumber = trim((string) ($data['konto'] ?? ''));
            $contraNumber = trim((string) ($data['gegenkonto'] ?? ''));

            if ($amount <= 0 || ! in_array($debitCredit, ['S', 'H'], true) || $accountNumber === '' || $contraNumber === '') {
                $skipped++;
                continue;
            }

            [$account, $accountCreated] = $this->resolveDatevAccount($tenantId, $accountNumber);
            [$contraAccount, $contraCreated] = $this->resolveDatevAccount($tenantId, $contraNumber);
            $createdAccounts += (int) $accountCreated + (int) $contraCreated;

            $accountFrom = $debitCredit === 'S' ? $contraAccount : $account;
            $accountTo = $debitCredit === 'S' ? $account : $contraAccount;
            $date = $this->parseDatevDate((string) ($data['belegdatum'] ?? ''), $fiscalYear);
            $status = $validated['status'];

            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'date' => $date,
                'description' => trim((string) ($data['buchungstext'] ?? 'DATEV-Import')),
                'amount' => $amount,
                'account_from_id' => $accountFrom->id,
                'account_to_id' => $accountTo->id,
                'tax_area' => $accountTo->tax_area ?: $accountFrom->tax_area ?: 'ideell',
                'receipt_number' => $receiptNumber,
                'status' => $status,
                'finalized_at' => $status === 'abgeschlossen' ? now() : null,
                'finalized_by' => $status === 'abgeschlossen' ? auth()->id() : null,
                'receipt_meta' => [
                    'source' => 'DATEV EXTF',
                    'file' => $request->file('datev_file')->getClientOriginalName(),
                    'row' => $rowNumber,
                    'belegfeld_1' => $data['belegfeld1'] ?? null,
                    'buchung_guid' => $data['buchungsguid'] ?? null,
                    'soll_haben' => $debitCredit,
                    'konto' => $accountNumber,
                    'gegenkonto' => $contraNumber,
                    'bu_schluessel' => $data['buschluessel'] ?? null,
                ],
            ]);

            $this->recalculateAccountBalances($tenantId, [
                $transaction->account_from_id,
                $transaction->account_to_id,
            ]);

            $imported++;
        }

        fclose($handle);

        return redirect()
            ->route('transactions.index')
            ->with('success', "{$imported} Buchungen importiert, {$skipped} Zeilen übersprungen. {$createdAccounts} fehlende Konten wurden als Importkonten angelegt.");
    }

    public function finalize(Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        if ($transaction->isCancelled()) {
            return back()->with('error', 'Stornobuchungen können nicht abgeschlossen werden.');
        }

        if ($transaction->isFinalized()) {
            return back()->with('success', 'Die Buchung war bereits abgeschlossen.');
        }

        if ($message = $this->invoicePaymentConflictMessage($transaction)) {
            return back()->with('error', $message);
        }

        $transaction->forceFill([
            'status' => 'abgeschlossen',
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ])->save();

        $this->syncInvoicePaymentForTransaction($transaction);
        $this->markLinkedReceiptDocumentPaid($transaction);

        $this->recalculateAccountBalances(auth()->user()->tenant_id, [
            $transaction->account_from_id,
            $transaction->account_to_id,
        ]);

        return back()->with('success', 'Die Buchung wurde abgeschlossen.');
    }

    public function finalizeSelected(Request $request)
    {
        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer'],
        ]);

        $transactions = Transaction::forCurrentTenant()
            ->whereIn('id', $validated['transaction_ids'])
            ->get();

        if ($transactions->isEmpty()) {
            return back()->with('error', 'Keine passenden Buchungen gefunden.');
        }

        $finalizedCount = 0;
        $blockedMessages = [];

        foreach ($transactions as $transaction) {
            if ($transaction->isCancelled() || $transaction->isFinalized()) {
                continue;
            }

            if ($message = $this->invoicePaymentConflictMessage($transaction)) {
                $blockedMessages[] = $message;
                continue;
            }

            $transaction->forceFill([
                'status' => 'abgeschlossen',
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ])->save();

            $this->syncInvoicePaymentForTransaction($transaction);
            $this->markLinkedReceiptDocumentPaid($transaction);

            $this->recalculateAccountBalances(auth()->user()->tenant_id, [
                $transaction->account_from_id,
                $transaction->account_to_id,
            ]);

            $finalizedCount++;
        }

        if ($finalizedCount === 0) {
            return back()->with('error', $blockedMessages[0] ?? 'Keine markierten Buchungen konnten abgeschlossen werden.');
        }

        $message = $finalizedCount . ' Buchung(en) wurden abgeschlossen.';
        if ($blockedMessages) {
            $message .= ' ' . count($blockedMessages) . ' Buchung(en) wurden wegen möglicher Doppelzahlung übersprungen.';
        }

        return back()->with('success', $message);
    }

    public function contractReceiptSelected(Request $request)
    {
        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer'],
            'contract_document_id' => ['nullable', Rule::exists('documents', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('category', Document::CATEGORY_CONTRACTS)],
            'contract_reference' => [Rule::requiredIf(fn () => blank($request->input('contract_document_id'))), 'nullable', 'string', 'max:255'],
            'contract_location' => ['nullable', 'string', 'max:255'],
            'contract_date' => ['nullable', 'date'],
        ]);

        $transactions = Transaction::forCurrentTenant()
            ->whereIn('id', $validated['transaction_ids'])
            ->get();

        if ($transactions->isEmpty()) {
            return back()->with('error', 'Keine passenden Buchungen gefunden.');
        }

        $updatedCount = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->isCancelled() || $transaction->hasAnyReceipt()) {
                continue;
            }

            $transaction->forceFill([
                'receipt_kind' => 'vertrag',
                'receipt_meta' => $this->contractReceiptMeta($validated),
                'updated_by' => auth()->id(),
            ])->save();

            $updatedCount++;
        }

        if ($updatedCount === 0) {
            return back()->with('error', 'Keine markierten Buchungen konnten als Vertragsnachweis aktualisiert werden.');
        }

        return back()->with('success', $updatedCount . ' Buchung(en) wurden als Vertrag/Dauerbeleg markiert.');
    }

    public function updateJournalCheck(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        $validated = $request->validate([
            'field' => ['required', Rule::in(['journal_reviewed', 'journal_receipt_checked'])],
            'checked' => ['required', 'boolean'],
        ]);

        $now = $validated['checked'] ? now() : null;
        $userId = $validated['checked'] ? auth()->id() : null;

        if ($validated['field'] === 'journal_reviewed') {
            $transaction->forceFill([
                'journal_reviewed_at' => $now,
                'journal_reviewed_by' => $userId,
                'updated_by' => auth()->id(),
            ])->save();
        }

        if ($validated['field'] === 'journal_receipt_checked') {
            $transaction->forceFill([
                'journal_receipt_checked_at' => $now,
                'journal_receipt_checked_by' => $userId,
                'updated_by' => auth()->id(),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'field' => $validated['field'],
            'checked' => (bool) $validated['checked'],
            'updated_at' => optional($now)->toIso8601String(),
            'display_time' => optional($now)->format('d.m. H:i'),
            'user_name' => auth()->user()?->name ?? 'Clubano',
        ]);
    }

    /**
     * 🔥 NEU: Edit
     */
    public function edit(Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        if ($transaction->isFinalized()) {
            return redirect()->route('transactions.index')
                ->with('error', 'Abgeschlossene Buchungen können nicht mehr bearbeitet werden. Bitte nutze bei Bedarf die Stornierung.');
        }

        $accounts = Account::forCurrentTenant()
            ->with('budgetCategory')
            ->orderBy('number')
            ->get();
        $budgetCategories = $this->budgetCategoryChoices();
        $invoices = $this->invoiceChoices($transaction->invoice_id);
        $contractDocuments = $this->contractDocumentChoices($transaction->receipt_meta['contract_document_id'] ?? null);

        return view('transactions.edit', compact('transaction', 'accounts', 'budgetCategories', 'invoices', 'contractDocuments'));
    }

    public function ownReceipt(Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        $tenant = auth()->user()->tenant;
        $receiptMeta = $transaction->receipt_meta ?? [];
        $defaultReceiptDirection = $receiptMeta['receipt_direction'] ?? $this->inferOwnReceiptDirection($transaction);

        return view('transactions.own-receipt', compact('transaction', 'tenant', 'receiptMeta', 'defaultReceiptDirection'));
    }

    public function storeOwnReceipt(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        $validated = $request->validate([
            'issuer_name' => ['required', 'string', 'max:255'],
            'issuer_role' => ['nullable', 'string', 'max:255'],
            'receipt_direction' => ['required', Rule::in(['income', 'expense'])],
            'expense_reason' => ['required', 'string', 'max:1000'],
            'missing_receipt_reason' => ['required', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'approved_by' => ['nullable', 'string', 'max:255'],
        ]);

        if ($transaction->receipt_file && !$transaction->hasOwnReceipt()) {
            return redirect()
                ->route('transactions.edit', $transaction)
                ->with('error', 'Für diese Buchung ist bereits ein externer Beleg hinterlegt. Ein Eigenbeleg ist nur sinnvoll, wenn kein anderer Beleg vorliegt.');
        }

        $tenant = auth()->user()->tenant;
        $receiptMeta = array_merge($validated, [
            'generated_at' => now()->toIso8601String(),
            'generated_by' => auth()->id(),
        ]);

        $pdf = Pdf::loadView('transactions.own-receipt-pdf', [
            'transaction' => $transaction,
            'tenant' => $tenant,
            'receiptMeta' => $receiptMeta,
            'receiptDirection' => $validated['receipt_direction'],
            'logoPath' => $tenant?->logo_storage_path && file_exists(storage_path('app/public/' . $tenant->logo_storage_path))
                ? storage_path('app/public/' . $tenant->logo_storage_path)
                : null,
            'receiptDocumentNumber' => 'EB-' . now()->format('Y') . '-' . str_pad((string) $transaction->id, 5, '0', STR_PAD_LEFT),
        ])->setPaper('a4');

        app(ReceiptStorage::class)->delete($transaction->receipt_file);

        $relativePath = 'receipts/' . auth()->user()->tenant_id . '/eigenbelege/eigenbeleg-' . $transaction->id . '-' . now()->format('YmdHis') . '.pdf';

        $receiptFile = app(ReceiptStorage::class)->putPdf($relativePath, $pdf->output());

        $transaction->forceFill([
            'receipt_file' => $receiptFile,
            'receipt_kind' => 'eigenbeleg',
            'receipt_meta' => $receiptMeta,
            'updated_by' => auth()->id(),
        ])->save();

        return redirect()
            ->route('transactions.own-receipt', $transaction)
            ->with('success', 'Der Eigenbeleg wurde erstellt und direkt an die Buchung gehängt.');
    }

    /**
     * 🔥 NEU: Update
     */
    public function update(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        if ($transaction->isFinalized()) {
            return redirect()->route('transactions.index')
                ->with('error', 'Abgeschlossene Buchungen können nicht mehr bearbeitet werden. Bitte nutze bei Bedarf die Stornierung.');
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_from_id' => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'account_to_id' => ['required', 'different:account_from_id', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)->where('document_type', 'invoice')],
            'tax_area' => ['required', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'budget_category_id' => ['nullable', Rule::exists('budget_categories', 'id')->where('tenant_id', $tenantId)->where('active', true)],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'receipt_kind' => ['nullable', Rule::in(['none', 'vertrag'])],
            'contract_document_id' => ['nullable', Rule::exists('documents', 'id')->where('tenant_id', $tenantId)->where('category', Document::CATEGORY_CONTRACTS)],
            'contract_reference' => [Rule::requiredIf(fn () => ($request->input('receipt_kind') === 'vertrag') && blank($request->input('contract_document_id'))), 'nullable', 'string', 'max:255'],
            'contract_location' => ['nullable', 'string', 'max:255'],
            'contract_date' => ['nullable', 'date'],
        ]);

        $affectedAccountIds = collect([
            $transaction->account_from_id,
            $transaction->account_to_id,
            $validated['account_from_id'],
            $validated['account_to_id'],
        ])->filter()->unique()->values();

        $transaction->update([
            'date' => $validated['date'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'account_from_id' => $validated['account_from_id'],
            'account_to_id' => $validated['account_to_id'],
            'invoice_id' => $validated['invoice_id'] ?? null,
            'tax_area' => $validated['tax_area'],
            'budget_category_id' => ($validated['budget_category_id'] ?? null) ?: $this->suggestBudgetCategoryId($validated['account_from_id'], $validated['account_to_id']),
            'updated_by' => auth()->id(),
        ]);

        // 🔥 Beleg ersetzen
        if ($request->hasFile('receipt_file')) {

            // alten Beleg löschen (wenn vorhanden)
            app(ReceiptStorage::class)->delete($transaction->receipt_file);

            $transaction->update([
                'receipt_file' => app(ReceiptStorage::class)->storeUploaded($request->file('receipt_file'), auth()->user()->tenant_id),
                'receipt_kind' => 'upload',
                'receipt_meta' => null,
            ]);
        } elseif (($validated['receipt_kind'] ?? 'none') === 'vertrag') {
            app(ReceiptStorage::class)->delete($transaction->receipt_file);

            $transaction->update([
                'receipt_file' => null,
                'receipt_kind' => 'vertrag',
                'receipt_meta' => $this->contractReceiptMeta($validated),
            ]);
        } elseif (($validated['receipt_kind'] ?? null) === 'none' && $transaction->hasContractReceipt()) {
            $transaction->update([
                'receipt_kind' => null,
                'receipt_meta' => null,
            ]);
        }

        if ($transaction->invoice_id) {
            $this->markTransactionAsInvoiceReceipt($transaction);
            $transaction->save();
        } elseif ($transaction->receipt_kind === 'system_invoice') {
            $transaction->forceFill([
                'receipt_kind' => null,
                'receipt_meta' => null,
            ])->save();
        }

        $this->recalculateAccountBalances($tenantId, $affectedAccountIds);

        return redirect()->route('transactions.index')
            ->with('success', 'Buchung erfolgreich aktualisiert.');
    }

    public function cancel(Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        if ($transaction->isCancelled()) {
            return redirect()->route('transactions.index')
                ->with('error', 'Stornobuchungen können nicht erneut storniert werden.');
        }

        if (!$transaction->isFinalized()) {
            return redirect()->route('transactions.index')
                ->with('error', 'Bitte schließe die Buchung zuerst ab. Solange sie offen ist, kann sie noch direkt bearbeitet werden.');
        }

        return view('transactions.cancel', compact('transaction'));
    }

    public function cancelStore(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        if ($transaction->isCancelled()) {
            return redirect()->route('transactions.index')
                ->with('error', 'Stornobuchungen können nicht erneut storniert werden.');
        }

        if (!$transaction->isFinalized()) {
            return redirect()->route('transactions.index')
                ->with('error', 'Bitte schließe die Buchung zuerst ab. Solange sie offen ist, kann sie noch direkt bearbeitet werden.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $stornoPrefix = $this->stornoPrefix($transaction);

        $alreadyCancelled = Transaction::forCurrentTenant()
            ->where('description', 'like', $stornoPrefix . '%')
            ->exists();

        if ($alreadyCancelled) {
            return redirect()->route('transactions.index')
                ->with('error', 'Für diese Buchung existiert bereits eine Stornobuchung.');
        }

        $latest = Transaction::orderBy('id', 'desc')->first();
        $nextNumber = $latest ? $latest->id + 1 : 1;
        $receiptNumber = 'TRX-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $storno = new Transaction();
        $storno->tenant_id = auth()->user()->tenant_id;
        $storno->created_by = auth()->id();
        $storno->updated_by = auth()->id();
        $storno->status = 'abgeschlossen';
        $storno->finalized_at = now();
        $storno->finalized_by = auth()->id();
        $storno->date = now()->toDateString();
        $storno->description = $stornoPrefix . ' – ' . trim($validated['reason']);
        $storno->amount = $transaction->amount;
        $storno->account_from_id = $transaction->account_to_id;
        $storno->account_to_id = $transaction->account_from_id;
        $storno->tax_area = $transaction->tax_area;
        $storno->receipt_number = $receiptNumber;
        $storno->save();

        $this->recalculateAccountBalances(auth()->user()->tenant_id, [
            $storno->account_from_id,
            $storno->account_to_id,
        ]);

        return redirect()->route('transactions.index')
            ->with('success', 'Buchung wurde storniert. Die Gegenbuchung ist jetzt im Journal und im Kassenbuch sichtbar.');
    }

    private function getJournalData(Request $request)
    {
        $filter = $request->input('filter');
        $year = $request->input('year');
        $month = $request->input('month');
        $contractDocuments = $this->contractDocumentChoices();

        $transactions = Transaction::forCurrentTenant()
            ->with(['account_from', 'account_to', 'creator', 'updater', 'finalizer', 'journalReviewer', 'journalReceiptChecker', 'invoice'])
            ->orderBy('date');

        if ($filter === 'income') {
            $transactions->whereHas('account_from', fn($q) => $q->where('type', 'einnahme'));
        }

        if ($filter === 'expense') {
            $transactions->whereHas('account_to', fn($q) => $q->where('type', 'ausgabe'));
        }

        if ($filter === 'storno') {
            $transactions->where(function ($query) {
                $query->where('description', 'like', 'Storno:%')
                    ->orWhere('description', 'like', 'Storno zu %');
            });
        }

        if ($filter === 'missing_receipt') {
            $transactions->where(function ($query) {
                $query->whereNull('receipt_file')
                    ->orWhere('receipt_file', '');
            })->whereNull('invoice_id')
            ->where(function ($query) {
                $query->whereNull('receipt_kind')
                    ->orWhereNotIn('receipt_kind', ['vertrag', 'system_invoice']);
            })->where(function ($query) {
                $query->whereNull('receipt_kind')
                    ->orWhere('receipt_kind', '!=', 'vertrag');
            });
        }

        if ($year) {
            $transactions->whereYear('date', $year);
        }

        if ($month) {
            $transactions->whereMonth('date', $month);
        }

        $transactions = $transactions->get();

        $totalIncome = $transactions
            ->filter(fn($t) => in_array(optional($t->account_to)->type, ['bank','kasse']))
            ->sum('amount');

        $totalExpense = $transactions
            ->filter(fn($t) => in_array(optional($t->account_from)->type, ['bank','kasse']))
            ->sum('amount');

        $saldo = $totalIncome - $totalExpense;

        $tenant = auth()->user()->tenant;

        return compact(
            'transactions',
            'filter',
            'year',
            'month',
            'tenant',
            'totalIncome',
            'totalExpense',
            'saldo',
            'contractDocuments'
        );
    }

    protected function normalizeDatevHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $normalized = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->toString();

        return match ($normalized) {
            'umsatzohnesollhaben kz', 'umsatzohnesollhabenkennzeichen', 'umsatzohnesollhabenkz' => 'umsatz',
            'sollhabenkennzeichen' => 'sollhabenkennzeichen',
            'gegenkontoohnebuschlussel', 'gegenkontoohnebuschluessel' => 'gegenkonto',
            'buschlussel', 'buschluessel' => 'buschluessel',
            'belegfeld1' => 'belegfeld1',
            'buchungsguid' => 'buchungsguid',
            default => $normalized,
        };
    }

    /**
     * @param array<int, string> $header
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    protected function mapDatevRow(array $header, array $row): array
    {
        $mapped = [];

        foreach ($header as $index => $key) {
            $mapped[$key] = $row[$index] ?? null;
        }

        return $mapped;
    }

    protected function datevFiscalYear(array $meta): int
    {
        foreach ([14, 12, 15] as $index) {
            $value = (string) ($meta[$index] ?? '');

            if (preg_match('/^\d{8}$/', $value)) {
                return (int) substr($value, 0, 4);
            }
        }

        return (int) now()->year;
    }

    protected function parseDatevDate(string $value, int $year): string
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($value) === 8) {
            return Carbon::createFromFormat('dmY', $value)->toDateString();
        }

        if (strlen($value) === 4) {
            return Carbon::createFromFormat('dmY', $value . $year)->toDateString();
        }

        return now()->toDateString();
    }

    protected function parseDatevAmount(string $value): float
    {
        $value = trim($value);
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return round((float) $value, 2);
    }

    /**
     * @return array{0: Account, 1: bool}
     */
    protected function resolveDatevAccount(string $tenantId, string $number): array
    {
        $account = Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('number', $number)
            ->orderByDesc('active')
            ->orderBy('id')
            ->first();

        if ($account) {
            return [$account, false];
        }

        return [Account::create([
            'tenant_id' => $tenantId,
            'number' => $number,
            'name' => 'Importkonto ' . $number,
            'type' => $this->inferDatevAccountType($number),
            'tax_area' => 'ideell',
            'chart_name' => 'DATEV Import',
            'is_postable' => true,
            'datev_automatic' => false,
            'active' => true,
            'online' => false,
            'balance_start' => 0,
            'balance_current' => 0,
            'import_source' => 'DATEV Buchungsstapel',
        ]), true];
    }

    protected function inferDatevAccountType(string $number): string
    {
        if (in_array($number, ['1000', '1200', '1220', '1360', '1361'], true)) {
            return str_starts_with($number, '12') ? 'bank' : 'kasse';
        }

        if (str_starts_with($number, '8')) {
            return 'einnahme';
        }

        return 'ausgabe';
    }

    public function journal(Request $request)
    {
        $data = $this->getJournalData($request);
        $data['isPdf'] = false;
        return view('transactions.journal', $data);
    }

    public function eur(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $start = $request->input('start', Carbon::now()->startOfYear()->format('Y-m-d'));
        $end = $request->input('end', Carbon::now()->endOfYear()->format('Y-m-d'));

        $transactions = Transaction::where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->with(['account_from', 'account_to', 'creator', 'updater', 'finalizer'])
            ->get();

        $totalIncome = $transactions
            ->filter(fn($t) => optional($t->account_from)->type === 'einnahme')
            ->sum('amount');

        $totalExpense = $transactions
            ->filter(fn($t) => optional($t->account_to)->type === 'ausgabe')
            ->sum('amount');

        $areas = [
            'ideell' => 'Ideeller Bereich',
            'zweckbetrieb' => 'Zweckbetrieb',
            'vermoegensverwaltung' => 'Vermögensverwaltung',
            'wirtschaftlich' => 'Wirtschaftlicher Geschäftsbetrieb',
        ];

        $result = collect($areas)->mapWithKeys(function ($label, $area) use ($transactions) {
            $items = $transactions->where('tax_area', $area);

            $income = $items
                ->filter(fn($t) => optional($t->account_from)->type === 'einnahme')
                ->sum('amount');

            $expense = $items
                ->filter(fn($t) => optional($t->account_to)->type === 'ausgabe')
                ->sum('amount');

            return [$area => [
                'label' => $label,
                'income' => $income,
                'expense' => $expense,
                'saldo' => $income - $expense,
                'count' => $items->count(),
            ]];
        });

        $activeAreaCount = $result->filter(fn ($row) => $row['count'] > 0)->count();

        return view('transactions.eur', [
            'transactions' => $transactions,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'saldo' => $totalIncome - $totalExpense,
            'result' => $result,
            'activeAreaCount' => $activeAreaCount,
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function corporationTax(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $start = $request->input('start', Carbon::now()->startOfYear()->format('Y-m-d'));
        $end = $request->input('end', Carbon::now()->endOfYear()->format('Y-m-d'));

        $transactions = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'abgeschlossen')
            ->whereBetween('date', [$start, $end])
            ->with(['account_from', 'account_to', 'creator', 'updater', 'finalizer'])
            ->orderBy('date')
            ->get();

        $allAreas = [
            'ideell' => 'Ideeller Bereich',
            'zweckbetrieb' => 'Zweckbetrieb',
            'vermoegensverwaltung' => 'Vermögensverwaltung',
            'wirtschaftlich' => 'Wirtschaftlicher Geschäftsbetrieb',
        ];

        $areaResults = collect($allAreas)->mapWithKeys(function ($label, $area) use ($transactions) {
            $items = $transactions->where('tax_area', $area);

            $income = $items
                ->filter(fn ($transaction) => optional($transaction->account_from)->type === 'einnahme')
                ->sum('amount');

            $expense = $items
                ->filter(fn ($transaction) => optional($transaction->account_to)->type === 'ausgabe')
                ->sum('amount');

            return [$area => [
                'label' => $label,
                'income' => $income,
                'expense' => $expense,
                'saldo' => $income - $expense,
                'count' => $items->count(),
            ]];
        });

        $relevantAreas = collect(['vermoegensverwaltung', 'wirtschaftlich'])
            ->mapWithKeys(fn ($area) => [$area => $areaResults[$area]])
            ->all();

        $relevantTransactions = $transactions
            ->filter(fn ($transaction) => in_array($transaction->tax_area, ['vermoegensverwaltung', 'wirtschaftlich'], true))
            ->values();

        $pendingCount = Transaction::where('tenant_id', $tenantId)
            ->where('status', '!=', 'abgeschlossen')
            ->whereBetween('date', [$start, $end])
            ->count();

        $relevantIncome = collect($relevantAreas)->sum('income');
        $relevantExpense = collect($relevantAreas)->sum('expense');
        $relevantSaldo = $relevantIncome - $relevantExpense;

        return view('transactions.corporation-tax', [
            'start' => $start,
            'end' => $end,
            'allAreas' => $areaResults,
            'relevantAreas' => $relevantAreas,
            'relevantTransactions' => $relevantTransactions,
            'pendingCount' => $pendingCount,
            'relevantIncome' => $relevantIncome,
            'relevantExpense' => $relevantExpense,
            'relevantSaldo' => $relevantSaldo,
        ]);
    }

    public function summary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $start = $request->input('start', Carbon::now()->startOfYear()->format('Y-m-d'));
        $end = $request->input('end', Carbon::now()->endOfYear()->format('Y-m-d'));

        $transactions = Transaction::where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->with(['account_from.budgetCategory', 'account_to.budgetCategory', 'budgetCategory', 'creator', 'updater', 'finalizer', 'invoice'])
            ->orderByDesc('date')
            ->get();

        $totalIncome = $transactions
            ->filter(fn($t) => optional($t->account_from)->type === 'einnahme')
            ->sum('amount');

        $totalExpense = $transactions
            ->filter(fn($t) => optional($t->account_to)->type === 'ausgabe')
            ->sum('amount');

        $missingReceiptCount = $transactions->filter(fn ($transaction) => !$transaction->hasAnyReceipt())->count();
        $pendingCount = $transactions->where('status', '!=', 'abgeschlossen')->count();
        $systemReceiptCount = $transactions->filter(fn ($transaction) => $transaction->hasSystemReceipt())->count();
        $pendingTransactions = $transactions
            ->where('status', '!=', 'abgeschlossen')
            ->take(5)
            ->values();
        $missingReceiptTransactions = $transactions
            ->filter(fn ($transaction) => !$transaction->hasAnyReceipt())
            ->take(5)
            ->values();
        $categorySummaries = $this->transactionCategorySummaries($transactions);
        $duplicateTransactionGroups = $this->duplicateTransactionGroups($transactions);
        $today = Carbon::today();

        $openInvoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'open')
            ->with(['items', 'payments'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->getRemainingAmount() > 0.009)
            ->values();

        $overdueInvoices = $openInvoices
            ->filter(fn (Invoice $invoice) => $invoice->due_date && $invoice->due_date->lt($today))
            ->values();
        $dueSoonInvoices = $openInvoices
            ->filter(fn (Invoice $invoice) => $invoice->due_date && $invoice->due_date->betweenIncluded($today, $today->copy()->addDays(14)))
            ->values();

        $receiptReviewDocuments = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->notArchived()
            ->whereIn('receipt_status', [Document::RECEIPT_NEEDS_REVIEW, Document::RECEIPT_READY])
            ->latest('updated_at')
            ->limit(5)
            ->get();
        $payableReceiptCount = Document::where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->notArchived()
            ->where('receipt_status', Document::RECEIPT_READY)
            ->count();
        $payableReceiptTotal = Document::where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->notArchived()
            ->where('receipt_status', Document::RECEIPT_READY)
            ->sum('recognized_amount');

        $bankWorkQueue = BankTransaction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [BankTransaction::STATUS_PENDING, BankTransaction::STATUS_READY, BankTransaction::STATUS_DUPLICATE])
            ->latest('booking_date')
            ->limit(5)
            ->get();

        $bankWorkStats = [
            'pending' => BankTransaction::where('tenant_id', $tenantId)->where('status', BankTransaction::STATUS_PENDING)->count(),
            'ready' => BankTransaction::where('tenant_id', $tenantId)->where('status', BankTransaction::STATUS_READY)->count(),
            'duplicate' => BankTransaction::where('tenant_id', $tenantId)->where('status', BankTransaction::STATUS_DUPLICATE)->count(),
        ];

        $cashAndBankAccounts = Account::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['bank', 'kasse'])
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('number')
            ->get();

        return view('transactions.summary', [
            'transactions' => $transactions,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'saldo' => $totalIncome - $totalExpense,
            'missingReceiptCount' => $missingReceiptCount,
            'pendingCount' => $pendingCount,
            'systemReceiptCount' => $systemReceiptCount,
            'pendingTransactions' => $pendingTransactions,
            'missingReceiptTransactions' => $missingReceiptTransactions,
            'categorySummaries' => $categorySummaries,
            'duplicateTransactionGroups' => $duplicateTransactionGroups,
            'openInvoices' => $openInvoices,
            'overdueInvoices' => $overdueInvoices,
            'dueSoonInvoices' => $dueSoonInvoices,
            'receiptReviewDocuments' => $receiptReviewDocuments,
            'payableReceiptCount' => $payableReceiptCount,
            'payableReceiptTotal' => $payableReceiptTotal,
            'bankWorkQueue' => $bankWorkQueue,
            'bankWorkStats' => $bankWorkStats,
            'cashAndBankAccounts' => $cashAndBankAccounts,
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function audit(Request $request)
    {
        return view('transactions.audit', $this->buildAuditData($request, false));
    }

    public function auditPdf(Request $request)
    {
        $data = $this->buildAuditData($request, true);

        $pdf = Pdf::loadView('transactions.audit', $data)->setPaper('a4', 'portrait');

        return $pdf->download('Kassenpruefung_' . $data['start'] . '_' . $data['end'] . '.pdf');
    }

    private function buildAuditData(Request $request, bool $isPdf = false): array
    {
        $tenantId = auth()->user()->tenant_id;
        $tenant = auth()->user()->tenant;
        $start = $request->input('start', Carbon::now()->startOfYear()->format('Y-m-d'));
        $end = $request->input('end', Carbon::now()->endOfYear()->format('Y-m-d'));
        $periodStart = Carbon::parse($start)->startOfDay();
        $periodEnd = Carbon::parse($end)->endOfDay();

        $transactions = Transaction::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->with(['account_from.budgetCategory', 'account_to.budgetCategory', 'budgetCategory', 'creator', 'updater', 'finalizer', 'invoice'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $cashAndBankAccounts = Account::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['bank', 'kasse'])
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('number')
            ->get();

        $accountRows = $cashAndBankAccounts->map(function (Account $account) use ($periodStart, $periodEnd) {
            $openingBalance = $this->accountBalanceAt($account, $periodStart->copy()->subDay()->endOfDay());
            $periodTransactions = Transaction::query()
                ->where('tenant_id', $account->tenant_id)
                ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->where(function ($query) use ($account) {
                    $query->where('account_from_id', $account->id)
                        ->orWhere('account_to_id', $account->id);
                })
                ->get();

            $periodIn = $periodTransactions->where('account_to_id', $account->id)->sum('amount');
            $periodOut = $periodTransactions->where('account_from_id', $account->id)->sum('amount');
            $closingBalance = $openingBalance + $periodIn - $periodOut;

            return [
                'account' => $account,
                'opening' => round($openingBalance, 2),
                'in' => round($periodIn, 2),
                'out' => round($periodOut, 2),
                'closing' => round($closingBalance, 2),
                'transaction_count' => $periodTransactions->count(),
            ];
        })->values();

        $totalIncome = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->account_from?->type === 'einnahme')
            ->sum('amount');
        $totalExpense = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->account_to?->type === 'ausgabe')
            ->sum('amount');
        $transferTotal = $transactions
            ->filter(fn (Transaction $transaction) => in_array($transaction->account_from?->type, ['bank', 'kasse'], true)
                && in_array($transaction->account_to?->type, ['bank', 'kasse'], true))
            ->sum('amount');

        $pendingTransactions = $transactions
            ->filter(fn (Transaction $transaction) => ! $transaction->isFinalized())
            ->values();
        $missingReceiptTransactions = $transactions
            ->filter(fn (Transaction $transaction) => ! $transaction->hasAnyReceipt())
            ->values();
        $uncheckedReceiptTransactions = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->isFinalized() && ! $transaction->isJournalReceiptChecked())
            ->values();
        $uncheckedReviewTransactions = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->isFinalized() && ! $transaction->isJournalReviewed())
            ->values();
        $correctionTransactions = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->isCancelled())
            ->values();
        $duplicateTransactionGroups = $this->duplicateTransactionGroups($transactions);

        $openInvoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'open')
            ->where(function ($query) use ($periodEnd) {
                $query->whereNull('due_date')
                    ->orWhereDate('due_date', '<=', $periodEnd->toDateString());
            })
            ->with(['items', 'payments'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->getRemainingAmount() > 0.009)
            ->values();

        $openPayables = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->notArchived()
            ->with(['linkedTransaction'])
            ->orderBy('payable_due_date')
            ->get()
            ->filter(fn (Document $document) => ! in_array($document->derivedPayableStatus(), [Document::PAYABLE_PAID, Document::PAYABLE_CANCELLED], true))
            ->filter(fn (Document $document) => ! $document->payable_due_date || $document->payable_due_date->lte($periodEnd))
            ->values();

        $issueCount = $pendingTransactions->count()
            + $missingReceiptTransactions->count()
            + $uncheckedReceiptTransactions->count()
            + $uncheckedReviewTransactions->count()
            + $duplicateTransactionGroups->count();

        return [
            'tenant' => $tenant,
            'start' => $periodStart->toDateString(),
            'end' => $periodEnd->toDateString(),
            'isPdf' => $isPdf,
            'transactions' => $transactions,
            'accountRows' => $accountRows,
            'totalOpening' => $accountRows->sum('opening'),
            'totalClosing' => $accountRows->sum('closing'),
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'saldo' => $totalIncome - $totalExpense,
            'transferTotal' => $transferTotal,
            'pendingTransactions' => $pendingTransactions,
            'missingReceiptTransactions' => $missingReceiptTransactions,
            'uncheckedReceiptTransactions' => $uncheckedReceiptTransactions,
            'uncheckedReviewTransactions' => $uncheckedReviewTransactions,
            'correctionTransactions' => $correctionTransactions,
            'duplicateTransactionGroups' => $duplicateTransactionGroups,
            'openInvoices' => $openInvoices,
            'openPayables' => $openPayables,
            'issueCount' => $issueCount,
        ];
    }

    private function accountBalanceAt(Account $account, Carbon $date): float
    {
        $sumIn = Transaction::query()
            ->where('tenant_id', $account->tenant_id)
            ->where('account_to_id', $account->id)
            ->whereDate('date', '<=', $date->toDateString())
            ->sum('amount');
        $sumOut = Transaction::query()
            ->where('tenant_id', $account->tenant_id)
            ->where('account_from_id', $account->id)
            ->whereDate('date', '<=', $date->toDateString())
            ->sum('amount');

        return round((float) ($account->balance_start ?? 0) + (float) $sumIn - (float) $sumOut, 2);
    }

    public function journalPdf(Request $request)
    {
        $data = $this->getJournalData($request);
        $data['isPdf'] = true;

        $pdf = Pdf::loadView('transactions.journal', $data)
            ->setPaper('a4', 'landscape');

        $year = $data['year'] ?? 'alle';
        $month = $data['month'] ?? 'alle';

        return $pdf->download("Buchungsjournal_{$year}_{$month}.pdf");
    }

    public function create(Request $request)
    {
        $accounts = Account::forCurrentTenant()->with('budgetCategory')->orderBy('number')->get();
        $cashAccounts = $accounts->where('type', 'kasse')->values();
        $bankAccounts = $accounts->where('type', 'bank')->values();
        $incomeAccounts = $accounts->where('type', 'einnahme')->values();
        $expenseAccounts = $accounts->where('type', 'ausgabe')->values();
        $budgetCategories = $this->budgetCategoryChoices();
        $invoices = $this->invoiceChoices();
        $contractDocuments = $this->contractDocumentChoices();

        $prefill = [
            'context' => $request->input('context'),
            'receipt_document_id' => $request->input('receipt_document_id'),
            'date' => $request->input('date', now()->format('Y-m-d')),
            'description' => $request->input('description'),
            'amount' => $request->input('amount'),
            'tax_area' => $request->input('tax_area'),
            'budget_category_id' => $request->input('budget_category_id') ?: $this->suggestBudgetCategoryId($request->input('account_from_id'), $request->input('account_to_id')),
            'account_from_id' => $request->input('account_from_id'),
            'account_to_id' => $request->input('account_to_id'),
        ];

        $guidedContexts = ['bar-einnahme', 'bar-ausgabe', 'bank-zu-kasse', 'kasse-zu-bank'];
        $guidedContext = in_array($prefill['context'], $guidedContexts, true) ? $prefill['context'] : null;

        return view('transactions.create', compact(
            'accounts',
            'cashAccounts',
            'bankAccounts',
            'incomeAccounts',
            'expenseAccounts',
            'budgetCategories',
            'invoices',
            'contractDocuments',
            'prefill',
            'guidedContext'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_from_id' => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'account_to_id' => ['required', 'different:account_from_id', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)->where('document_type', 'invoice')],
            'status' => ['required', Rule::in(['entwurf', 'abgeschlossen'])],
            'tax_area' => ['required', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'budget_category_id' => ['nullable', Rule::exists('budget_categories', 'id')->where('tenant_id', $tenantId)->where('active', true)],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'receipt_kind' => ['nullable', Rule::in(['none', 'vertrag'])],
            'receipt_document_id' => [
                'nullable',
                Rule::exists('documents', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_booking_receipt', true)),
            ],
            'contract_document_id' => ['nullable', Rule::exists('documents', 'id')->where('tenant_id', $tenantId)->where('category', Document::CATEGORY_CONTRACTS)],
            'contract_reference' => [Rule::requiredIf(fn () => ($request->input('receipt_kind') === 'vertrag') && blank($request->input('contract_document_id'))), 'nullable', 'string', 'max:255'],
            'contract_location' => ['nullable', 'string', 'max:255'],
            'contract_date' => ['nullable', 'date'],
        ]);

        $latest = Transaction::orderBy('id', 'desc')->first();
        $nextNumber = $latest ? $latest->id + 1 : 1;
        $receiptNumber = 'TRX-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $transaction = new Transaction();
        $transaction->tenant_id = $tenantId;
        $transaction->created_by = auth()->id();
        $transaction->updated_by = auth()->id();
        $transaction->date = $validated['date'];
        $transaction->description = $validated['description'];
        $transaction->amount = $validated['amount'];
        $transaction->account_from_id = $validated['account_from_id'];
        $transaction->account_to_id = $validated['account_to_id'];
        $transaction->invoice_id = $validated['invoice_id'] ?? null;
        $transaction->status = $validated['status'];
        $transaction->finalized_at = $validated['status'] === 'abgeschlossen' ? now() : null;
        $transaction->finalized_by = $validated['status'] === 'abgeschlossen' ? auth()->id() : null;
        $transaction->tax_area = $validated['tax_area'];
        $transaction->budget_category_id = ($validated['budget_category_id'] ?? null) ?: $this->suggestBudgetCategoryId($validated['account_from_id'], $validated['account_to_id']);
        $transaction->receipt_number = $receiptNumber;

        if ($request->hasFile('receipt_file')) {
            $transaction->receipt_file = app(ReceiptStorage::class)->storeUploaded($request->file('receipt_file'), auth()->user()->tenant_id);
            $transaction->receipt_kind = 'upload';
            $transaction->receipt_meta = null;
        } elseif (! blank($validated['receipt_document_id'] ?? null)) {
            $document = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('is_booking_receipt', true)
                ->whereKey($validated['receipt_document_id'])
                ->firstOrFail();

            if ($message = $this->bookingReceiptConflictMessage($document)) {
                return back()->withInput()->with('error', $message);
            }

            $transaction->receipt_kind = 'document';
            $transaction->receipt_meta = [
                'document_id' => $document->id,
                'document_title' => $document->title,
                'document_name' => $document->original_name,
                'linked_at' => now()->toIso8601String(),
                'linked_by' => auth()->id(),
            ];
        } elseif (($validated['receipt_kind'] ?? 'none') === 'vertrag') {
            $transaction->receipt_kind = 'vertrag';
            $transaction->receipt_meta = $this->contractReceiptMeta($validated);
        }

        if ($transaction->invoice_id) {
            $this->markTransactionAsInvoiceReceipt($transaction);
        }

        if (
            !Account::query()->where('tenant_id', $tenantId)->whereKey($validated['account_from_id'])->exists()
            || !Account::query()->where('tenant_id', $tenantId)->whereKey($validated['account_to_id'])->exists()
        ) {
            abort(403, 'Ungültige Kontenzuordnung.');
        }

        if ($transaction->isFinalized() && ($message = $this->invoicePaymentConflictMessage($transaction))) {
            return back()->withInput()->with('error', $message);
        }

        $transaction->save();

        if (! blank($validated['receipt_document_id'] ?? null)) {
            Document::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($validated['receipt_document_id'])
                ->update([
                    'receipt_status' => Document::RECEIPT_BOOKED,
                    'payable_status' => $transaction->isFinalized() ? Document::PAYABLE_PAID : Document::PAYABLE_OPEN,
                    'payable_paid_amount' => $transaction->isFinalized() ? $transaction->amount : 0,
                    'linked_transaction_id' => $transaction->id,
                    'updated_at' => now(),
                ]);
        }

        if ($transaction->isFinalized()) {
            $this->syncInvoicePaymentForTransaction($transaction);
            $this->markLinkedReceiptDocumentPaid($transaction);
        }

        $this->recalculateAccountBalances($tenantId, [
            $transaction->account_from_id,
            $transaction->account_to_id,
        ]);

        return redirect()->route('transactions.index')
            ->with('success', 'Buchung erfolgreich gespeichert.');
    }

    protected function authorizeTransaction(Transaction $transaction)
    {
        if (!$transaction || $transaction->tenant_id != auth()->user()->tenant_id) {
            abort(403, 'Kein Zugriff auf diese Buchung.');
        }
    }

    private function markLinkedReceiptDocumentPaid(Transaction $transaction): void
    {
        Document::query()
            ->where('tenant_id', $transaction->tenant_id)
            ->where('linked_transaction_id', $transaction->id)
            ->where('is_booking_receipt', true)
            ->update([
                'receipt_status' => Document::RECEIPT_BOOKED,
                'payable_status' => Document::PAYABLE_PAID,
                'payable_paid_amount' => $transaction->amount,
                'updated_at' => now(),
            ]);
    }

    private function bookingReceiptConflictMessage(Document $document): ?string
    {
        if ($document->receipt_status === Document::RECEIPT_BOOKED || $document->linked_transaction_id) {
            return 'Dieser Beleg ist bereits mit einer Buchung verknüpft. Es wurde keine zweite Buchung erzeugt.';
        }

        return null;
    }

    private function budgetCategoryChoices()
    {
        BudgetCategory::ensureDefaultsForTenant(auth()->user()->tenant_id);

        return BudgetCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function suggestBudgetCategoryId(mixed $accountFromId, mixed $accountToId): ?int
    {
        $accountIds = collect([$accountFromId, $accountToId])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($accountIds->isEmpty()) {
            return null;
        }

        $accounts = Account::query()
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $from = $accounts->get((int) $accountFromId);
        $to = $accounts->get((int) $accountToId);

        if ($from?->type === 'einnahme' && $from->budget_category_id) {
            return (int) $from->budget_category_id;
        }

        if ($to?->type === 'ausgabe' && $to->budget_category_id) {
            return (int) $to->budget_category_id;
        }

        return null;
    }

    private function categoryForTransaction(Transaction $transaction): ?BudgetCategory
    {
        if ($transaction->budgetCategory) {
            return $transaction->budgetCategory;
        }

        if ($transaction->account_from?->type === 'einnahme') {
            return $transaction->account_from->budgetCategory;
        }

        if ($transaction->account_to?->type === 'ausgabe') {
            return $transaction->account_to->budgetCategory;
        }

        return null;
    }

    private function transactionCategorySummaries($transactions)
    {
        return $transactions
            ->filter(fn (Transaction $transaction) => $transaction->account_from?->type === 'einnahme' || $transaction->account_to?->type === 'ausgabe')
            ->groupBy(function (Transaction $transaction) {
                $category = $this->categoryForTransaction($transaction);

                return $category ? 'category-' . $category->id : 'uncategorized';
            })
            ->map(function ($categoryTransactions) {
                $first = $categoryTransactions->first();
                $category = $this->categoryForTransaction($first);
                $income = $categoryTransactions
                    ->filter(fn (Transaction $transaction) => $transaction->account_from?->type === 'einnahme')
                    ->sum('amount');
                $expense = $categoryTransactions
                    ->filter(fn (Transaction $transaction) => $transaction->account_to?->type === 'ausgabe')
                    ->sum('amount');

                return [
                    'id' => $category?->id,
                    'name' => $category?->name ?? 'Ohne Bereich',
                    'income' => $income,
                    'expense' => $expense,
                    'result' => $income - $expense,
                    'transactions_count' => $categoryTransactions->count(),
                ];
            })
            ->sortBy(fn (array $summary) => $summary['name'] === 'Ohne Bereich' ? 'zzzz' : $summary['name'])
            ->values();
    }

    private function duplicateTransactionGroups($transactions)
    {
        return $transactions
            ->filter(fn (Transaction $transaction) => ! $transaction->isCancelled())
            ->groupBy(function (Transaction $transaction) {
                return implode('|', [
                    $transaction->date?->toDateString() ?: '',
                    number_format((float) $transaction->amount, 2, '.', ''),
                    (int) $transaction->account_from_id,
                    (int) $transaction->account_to_id,
                ]);
            })
            ->filter(fn ($group) => $group->count() > 1)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'date' => $first->date,
                    'amount' => (float) $first->amount,
                    'account_from' => $first->account_from,
                    'account_to' => $first->account_to,
                    'count' => $group->count(),
                    'transactions' => $group->sortByDesc('id')->values(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    private function contractReceiptMeta(array $validated): array
    {
        $document = null;

        if (! blank($validated['contract_document_id'] ?? null)) {
            $document = Document::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('category', Document::CATEGORY_CONTRACTS)
                ->whereKey($validated['contract_document_id'])
                ->first();
        }

        return [
            'contract_document_id' => $document?->id,
            'contract_document_title' => $document?->title,
            'contract_reference' => trim((string) (($validated['contract_reference'] ?? null) ?: $document?->title ?: '')),
            'contract_location' => blank($validated['contract_location'] ?? null)
                ? ($document ? 'Dokumentenablage / Verträge' : null)
                : trim((string) $validated['contract_location']),
            'contract_date' => blank($validated['contract_date'] ?? null)
                ? $document?->document_date?->toDateString()
                : $validated['contract_date'],
            'marked_at' => now()->toIso8601String(),
            'marked_by' => auth()->id(),
        ];
    }

    private function markTransactionAsInvoiceReceipt(Transaction $transaction): void
    {
        $invoice = Invoice::query()
            ->where('tenant_id', $transaction->tenant_id)
            ->whereKey($transaction->invoice_id)
            ->first();

        $transaction->forceFill([
            'receipt_kind' => 'system_invoice',
            'receipt_meta' => array_filter([
                'invoice_number' => $invoice?->invoice_number,
                'linked_at' => now()->toIso8601String(),
                'linked_by' => auth()->id(),
            ]),
        ]);
    }

    private function syncInvoicePaymentForTransaction(Transaction $transaction): void
    {
        if (! $transaction->invoice_id) {
            return;
        }

        $invoice = Invoice::query()
            ->where('tenant_id', $transaction->tenant_id)
            ->whereKey($transaction->invoice_id)
            ->first();

        if (! $invoice || ! $transaction->isFinalized()) {
            return;
        }

        $paymentAccountId = $this->paymentAccountIdForTransaction($transaction);

        Payment::updateOrCreate(
            [
                'tenant_id' => $transaction->tenant_id,
                'transaction_id' => $transaction->id,
            ],
            [
                'invoice_id' => $invoice->id,
                'account_id' => $paymentAccountId,
                'amount' => round((float) $transaction->amount, 2),
                'payment_date' => $transaction->date?->toDateString() ?: now()->toDateString(),
                'note' => 'Automatisch aus Geldbewegung ' . ($transaction->receipt_number ?: ('#' . $transaction->id)),
            ]
        );

        $this->refreshInvoicePaymentStatus($invoice);
    }

    private function invoicePaymentConflictMessage(Transaction $transaction): ?string
    {
        if (! $transaction->invoice_id) {
            return null;
        }

        $invoice = Invoice::query()
            ->where('tenant_id', $transaction->tenant_id)
            ->whereKey($transaction->invoice_id)
            ->with(['items', 'payments'])
            ->first();

        if (! $invoice || ! $invoice->isInvoice() || in_array($invoice->status, ['entwurf', 'storniert'], true)) {
            return null;
        }

        $amount = round((float) $transaction->amount, 2);
        $paymentAccountId = $this->paymentAccountIdForTransaction($transaction);
        $paymentDate = $transaction->date?->toDateString();

        $duplicatePayment = $invoice->payments
            ->first(function (Payment $payment) use ($transaction, $paymentAccountId, $amount, $paymentDate) {
                if ($transaction->id && (int) $payment->transaction_id === (int) $transaction->id) {
                    return false;
                }

                return (int) $payment->account_id === (int) $paymentAccountId
                    && $payment->payment_date?->toDateString() === $paymentDate
                    && round((float) $payment->amount, 2) === $amount;
            });

        if ($duplicatePayment) {
            return 'Diese Rechnung hat bereits eine passende Zahlung. Es wurde keine zweite Zahlung erzeugt.';
        }

        $paidWithoutThisTransaction = $invoice->payments
            ->reject(fn (Payment $payment) => $transaction->id && (int) $payment->transaction_id === (int) $transaction->id)
            ->sum('amount');
        $remaining = round(max(0, $invoice->getTotal() - (float) $paidWithoutThisTransaction), 2);

        if ($remaining <= 0.009) {
            return 'Diese Rechnung ist bereits vollständig bezahlt. Die Buchung wurde nicht abgeschlossen, damit keine Doppelzahlung entsteht.';
        }

        if ($amount - $remaining > 0.009) {
            return 'Der Buchungsbetrag ist höher als der offene Rechnungsbetrag von ' . number_format($remaining, 2, ',', '.') . ' EUR. Bitte Betrag oder Zuordnung prüfen.';
        }

        return null;
    }

    private function paymentAccountIdForTransaction(Transaction $transaction): ?int
    {
        $transaction->loadMissing(['account_from', 'account_to']);

        if (in_array(optional($transaction->account_to)->type, ['bank', 'kasse'], true)) {
            return $transaction->account_to_id;
        }

        if (in_array(optional($transaction->account_from)->type, ['bank', 'kasse'], true)) {
            return $transaction->account_from_id;
        }

        return null;
    }

    private function refreshInvoicePaymentStatus(Invoice $invoice): void
    {
        $invoice->refresh();

        if (! $invoice->isInvoice() || in_array($invoice->status, ['entwurf', 'storniert'], true)) {
            return;
        }

        $total = round((float) $invoice->getTotal(), 2);
        $paid = round((float) $invoice->payments()->sum('amount'), 2);

        if ($total <= 0 || $paid >= $total) {
            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ?: now(),
            ])->save();

            $invoice->eventBookings()->update(['payment_status' => 'paid']);

            return;
        }

        if ($paid > 0) {
            $invoice->forceFill([
                'status' => 'open',
                'paid_at' => null,
            ])->save();

            return;
        }

        $invoice->forceFill([
            'status' => 'open',
            'paid_at' => null,
        ])->save();
    }

    private function invoiceChoices(?int $selectedInvoiceId = null)
    {
        return Invoice::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('document_type', 'invoice')
            ->whereNotIn('status', ['entwurf', 'storniert'])
            ->when($selectedInvoiceId, function ($query) use ($selectedInvoiceId) {
                $query->orWhere(function ($orQuery) use ($selectedInvoiceId) {
                    $orQuery->where('tenant_id', auth()->user()->tenant_id)
                        ->whereKey($selectedInvoiceId);
                });
            })
            ->withSum('payments as paid_amount', 'amount')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    private function contractDocumentChoices(?int $selectedDocumentId = null)
    {
        return Document::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('category', Document::CATEGORY_CONTRACTS)
            ->notArchived()
            ->when($selectedDocumentId, function ($query) use ($selectedDocumentId) {
                $query->orWhere(function ($orQuery) use ($selectedDocumentId) {
                    $orQuery->where('tenant_id', auth()->user()->tenant_id)
                        ->whereKey($selectedDocumentId);
                });
            })
            ->orderBy('title')
            ->get();
    }

    protected function recalculateAccountBalances(string $tenantId, iterable $accountIds): void
    {
        $ids = collect($accountIds)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        Account::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->get()
            ->each(fn (Account $account) => $account->updateBalance());
    }

    private function cashDeltaForAccount(Transaction $transaction, int $cashAccountId): float
    {
        if ((int) $transaction->account_to_id === $cashAccountId) {
            return (float) $transaction->amount;
        }

        if ((int) $transaction->account_from_id === $cashAccountId) {
            return -1 * (float) $transaction->amount;
        }

        return 0.0;
    }

    private function cashMovementLabel(Transaction $transaction, int $cashAccountId): string
    {
        $fromType = optional($transaction->account_from)->type;
        $toType = optional($transaction->account_to)->type;

        if ((int) $transaction->account_to_id === $cashAccountId && $fromType === 'bank') {
            return 'Umbuchung Bank -> Kasse';
        }

        if ((int) $transaction->account_from_id === $cashAccountId && $toType === 'bank') {
            return 'Umbuchung Kasse -> Bank';
        }

        if ((int) $transaction->account_to_id === $cashAccountId) {
            return 'Bareinnahme';
        }

        if ((int) $transaction->account_from_id === $cashAccountId) {
            return 'Barausgabe';
        }

        return 'Kassenbewegung';
    }

    private function cashOpeningBalance(int $cashAccountId, Carbon $periodStart, float $baseBalance): float
    {
        $balance = $baseBalance;

        $previousTransactions = Transaction::forCurrentTenant()
            ->where(function ($query) use ($cashAccountId) {
                $query->where('account_from_id', $cashAccountId)
                    ->orWhere('account_to_id', $cashAccountId);
            })
            ->whereDate('date', '<', $periodStart->toDateString())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($previousTransactions as $transaction) {
            $balance += $this->cashDeltaForAccount($transaction, $cashAccountId);
        }

        return $balance;
    }

    private function buildCashbookData(Request $request, bool $paginated = true): array
    {
        $cashAccounts = Account::forCurrentTenant()
            ->where('active', true)
            ->where('type', 'kasse')
            ->orderBy('number')
            ->orderBy('name')
            ->get();

        $selectedCashAccount = $cashAccounts->firstWhere('id', (int) $request->input('account'))
            ?? $cashAccounts->first();

        $selectedYear = (int) $request->input('year', now()->year);
        $selectedMonth = $request->filled('month') ? (int) $request->input('month') : null;
        $movement = $request->input('movement', 'all');

        $periodStart = $selectedMonth
            ? Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth()
            : Carbon::create($selectedYear, 1, 1)->startOfYear();

        $periodEnd = $selectedMonth
            ? (clone $periodStart)->endOfMonth()
            : (clone $periodStart)->endOfYear();

        $bankAccounts = Account::forCurrentTenant()
            ->where('active', true)
            ->where('type', 'bank')
            ->orderBy('number')
            ->orderBy('name')
            ->get();

        if (!$selectedCashAccount) {
            return [
                'cashAccounts' => $cashAccounts,
                'selectedCashAccount' => null,
                'bankAccounts' => $bankAccounts,
                'transactions' => $paginated ? new LengthAwarePaginator([], 0, 25) : collect(),
                'selectedYear' => $selectedYear,
                'selectedMonth' => $selectedMonth,
                'movement' => $movement,
                'openingBalance' => 0,
                'periodIncome' => 0,
                'periodExpense' => 0,
                'closingBalance' => 0,
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
            ];
        }

        $baseQuery = Transaction::forCurrentTenant()
            ->with(['account_from', 'account_to', 'creator', 'updater', 'finalizer'])
            ->where(function ($query) use ($selectedCashAccount) {
                $query->where('account_from_id', $selectedCashAccount->id)
                    ->orWhere('account_to_id', $selectedCashAccount->id);
            })
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()]);

        if ($movement === 'income') {
            $baseQuery->where('account_to_id', $selectedCashAccount->id);
        }

        if ($movement === 'expense') {
            $baseQuery->where('account_from_id', $selectedCashAccount->id);
        }

        if ($movement === 'transfer') {
            $baseQuery->where(function ($query) use ($selectedCashAccount) {
                $query->where(function ($inner) use ($selectedCashAccount) {
                    $inner->where('account_to_id', $selectedCashAccount->id)
                        ->whereHas('account_from', fn ($accountQuery) => $accountQuery->where('type', 'bank'));
                })->orWhere(function ($inner) use ($selectedCashAccount) {
                    $inner->where('account_from_id', $selectedCashAccount->id)
                        ->whereHas('account_to', fn ($accountQuery) => $accountQuery->where('type', 'bank'));
                });
            });
        }

        $openingBalance = $this->cashOpeningBalance(
            $selectedCashAccount->id,
            $periodStart,
            (float) ($selectedCashAccount->balance_start ?? 0)
        );

        $runningBalance = $openingBalance;

        $cashbookRows = $baseQuery
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function ($transaction) use ($selectedCashAccount, &$runningBalance) {
                $delta = $this->cashDeltaForAccount($transaction, $selectedCashAccount->id);
                $affectsBalance = true;

                if ($affectsBalance) {
                    $runningBalance += $delta;
                }

                $transaction->cash_delta = $delta;
                $transaction->cash_effective_delta = $affectsBalance ? $delta : 0.0;
                $transaction->cash_affects_balance = $affectsBalance;
                $transaction->cash_balance = $runningBalance;
                $transaction->cash_direction = $delta >= 0 ? 'income' : 'expense';
                $transaction->counter_account = (int) $transaction->account_to_id === (int) $selectedCashAccount->id
                    ? $transaction->account_from
                    : $transaction->account_to;
                $transaction->cash_label = $this->cashMovementLabel($transaction, $selectedCashAccount->id);

                return $transaction;
            });

        $periodIncome = $cashbookRows
            ->filter(fn ($transaction) => $transaction->cash_effective_delta > 0)
            ->sum('cash_effective_delta');

        $periodExpense = $cashbookRows
            ->filter(fn ($transaction) => $transaction->cash_effective_delta < 0)
            ->sum(fn ($transaction) => abs((float) $transaction->cash_effective_delta));

        $closingBalance = optional($cashbookRows->last())->cash_balance
            ?? ((float) ($selectedCashAccount->balance_start ?? 0));

        $transactions = $paginated
            ? new LengthAwarePaginator(
                $cashbookRows->reverse()->values()->slice((LengthAwarePaginator::resolveCurrentPage() - 1) * 25, 25)->values(),
                $cashbookRows->count(),
                25,
                LengthAwarePaginator::resolveCurrentPage(),
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            )
            : $cashbookRows->values();

        return [
            'cashAccounts' => $cashAccounts,
            'selectedCashAccount' => $selectedCashAccount,
            'bankAccounts' => $bankAccounts,
            'transactions' => $transactions,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'movement' => $movement,
            'openingBalance' => $openingBalance,
            'periodIncome' => $periodIncome,
            'periodExpense' => $periodExpense,
            'closingBalance' => $closingBalance,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ];
    }

    private function isStornoTransaction(Transaction $transaction): bool
    {
        return $transaction->isCancelled();
    }

    private function inferOwnReceiptDirection(Transaction $transaction): string
    {
        $transaction->loadMissing(['account_from', 'account_to']);

        if ($transaction->account_from?->type === 'einnahme') {
            return 'income';
        }

        if ($transaction->account_to?->type === 'ausgabe') {
            return 'expense';
        }

        return 'expense';
    }

    private function stornoPrefix(Transaction $transaction): string
    {
        return 'Storno zu ' . ($transaction->receipt_number ?: ('Buchung #' . $transaction->id));
    }
}
