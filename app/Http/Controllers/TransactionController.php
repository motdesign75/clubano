<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Tenant;
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
            ->with(['account_from', 'account_to', 'creator', 'updater', 'finalizer'])
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
            })->where(function ($query) {
                $query->where('description', 'not like', 'Zahlung Rechnung %')
                    ->where('description', 'not like', 'Zahlung Angebot %');
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
                $exists = $transaction->receipt_file
                    ? Storage::disk('public')->exists($transaction->receipt_file)
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

        return view('transactions.index', compact('transactions', 'filter', 'year', 'month', 'search', 'summary'));
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

            if ($transaction->isFinalized()) {
                $this->recalculateAccountBalances($tenantId, [
                    $transaction->account_from_id,
                    $transaction->account_to_id,
                ]);
            }

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

        $transaction->forceFill([
            'status' => 'abgeschlossen',
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ])->save();

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

        foreach ($transactions as $transaction) {
            if ($transaction->isCancelled() || $transaction->isFinalized()) {
                continue;
            }

            $transaction->forceFill([
                'status' => 'abgeschlossen',
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ])->save();

            $this->recalculateAccountBalances(auth()->user()->tenant_id, [
                $transaction->account_from_id,
                $transaction->account_to_id,
            ]);

            $finalizedCount++;
        }

        if ($finalizedCount === 0) {
            return back()->with('error', 'Keine markierten Buchungen konnten abgeschlossen werden.');
        }

        return back()->with('success', $finalizedCount . ' Buchung(en) wurden abgeschlossen.');
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
            ->orderBy('number')
            ->get();

        return view('transactions.edit', compact('transaction', 'accounts'));
    }

    public function ownReceipt(Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        $tenant = auth()->user()->tenant;
        $receiptMeta = $transaction->receipt_meta ?? [];

        return view('transactions.own-receipt', compact('transaction', 'tenant', 'receiptMeta'));
    }

    public function storeOwnReceipt(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        $validated = $request->validate([
            'issuer_name' => ['required', 'string', 'max:255'],
            'issuer_role' => ['nullable', 'string', 'max:255'],
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
            'logoPath' => $tenant?->logo_storage_path && file_exists(storage_path('app/public/' . $tenant->logo_storage_path))
                ? storage_path('app/public/' . $tenant->logo_storage_path)
                : null,
            'receiptDocumentNumber' => 'EB-' . now()->format('Y') . '-' . str_pad((string) $transaction->id, 5, '0', STR_PAD_LEFT),
        ])->setPaper('a4');

        if ($transaction->receipt_file && Storage::disk('public')->exists($transaction->receipt_file)) {
            Storage::disk('public')->delete($transaction->receipt_file);
        }

        $relativePath = 'receipts/' . auth()->user()->tenant_id . '/eigenbelege/eigenbeleg-' . $transaction->id . '-' . now()->format('YmdHis') . '.pdf';

        Storage::disk('public')->put($relativePath, $pdf->output());

        $transaction->forceFill([
            'receipt_file' => $relativePath,
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
            'tax_area' => ['required', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
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
            'tax_area' => $validated['tax_area'],
            'updated_by' => auth()->id(),
        ]);

        // 🔥 Beleg ersetzen
        if ($request->hasFile('receipt_file')) {

            // alten Beleg löschen (wenn vorhanden)
            if ($transaction->receipt_file && Storage::disk('public')->exists($transaction->receipt_file)) {
                Storage::disk('public')->delete($transaction->receipt_file);
            }

            $transaction->update([
                'receipt_file' => $request->file('receipt_file')->store(
                    'receipts/' . auth()->user()->tenant_id,
                    'public'
                ),
                'receipt_kind' => 'upload',
                'receipt_meta' => null,
            ]);
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

        $transactions = Transaction::forCurrentTenant()
            ->with(['account_from', 'account_to', 'creator', 'updater', 'finalizer', 'journalReviewer', 'journalReceiptChecker'])
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
            })->where(function ($query) {
                $query->where('description', 'not like', 'Zahlung Rechnung %')
                    ->where('description', 'not like', 'Zahlung Angebot %');
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
            'saldo'
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
            ->with(['account_from', 'account_to', 'creator', 'updater', 'finalizer'])
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
            'start' => $start,
            'end' => $end,
        ]);
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
        $accounts = Account::forCurrentTenant()->orderBy('number')->get();
        $cashAccounts = $accounts->where('type', 'kasse')->values();
        $bankAccounts = $accounts->where('type', 'bank')->values();
        $incomeAccounts = $accounts->where('type', 'einnahme')->values();
        $expenseAccounts = $accounts->where('type', 'ausgabe')->values();

        $prefill = [
            'context' => $request->input('context'),
            'date' => $request->input('date', now()->format('Y-m-d')),
            'description' => $request->input('description'),
            'amount' => $request->input('amount'),
            'tax_area' => $request->input('tax_area'),
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
            'status' => ['required', Rule::in(['entwurf', 'abgeschlossen'])],
            'tax_area' => ['required', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
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
        $transaction->status = $validated['status'];
        $transaction->finalized_at = $validated['status'] === 'abgeschlossen' ? now() : null;
        $transaction->finalized_by = $validated['status'] === 'abgeschlossen' ? auth()->id() : null;
        $transaction->tax_area = $validated['tax_area'];
        $transaction->receipt_number = $receiptNumber;

        if ($request->hasFile('receipt_file')) {
            $transaction->receipt_file = $request->file('receipt_file')->store(
                'receipts/' . auth()->user()->tenant_id,
                'public'
            );
            $transaction->receipt_kind = 'upload';
            $transaction->receipt_meta = null;
        }

        if (
            !Account::query()->where('tenant_id', $tenantId)->whereKey($validated['account_from_id'])->exists()
            || !Account::query()->where('tenant_id', $tenantId)->whereKey($validated['account_to_id'])->exists()
        ) {
            abort(403, 'Ungültige Kontenzuordnung.');
        }

        $transaction->save();

        if ($transaction->isFinalized()) {
            $this->recalculateAccountBalances($tenantId, [
                $transaction->account_from_id,
                $transaction->account_to_id,
            ]);
        }

        return redirect()->route('transactions.index')
            ->with('success', 'Buchung erfolgreich gespeichert.');
    }

    protected function authorizeTransaction(Transaction $transaction)
    {
        if (!$transaction || $transaction->tenant_id != auth()->user()->tenant_id) {
            abort(403, 'Kein Zugriff auf diese Buchung.');
        }
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

    private function stornoPrefix(Transaction $transaction): string
    {
        return 'Storno zu ' . ($transaction->receipt_number ?: ('Buchung #' . $transaction->id));
    }
}
