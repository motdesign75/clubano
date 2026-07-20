<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::forCurrentTenant()
            ->with(['account_from', 'account_to'])
            ->orderByDesc('date')
            ->get()
            ->map(function ($transaction) {
                // Prüfen, ob Datei vorhanden ist
                if ($transaction->receipt_file) {
                    $path = storage_path('app/public/receipts/' . $transaction->receipt_file);
                    $transaction->receipt_exists = file_exists($path);
                } else {
                    $transaction->receipt_exists = false;
                }
                return $transaction;
            });

        return view('transactions.index', compact('transactions'));
    }

    public function create()
    {
        $accounts = Account::forCurrentTenant()->orderBy('number')->get();
        return view('transactions.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_from_id' => ['required', 'exists:accounts,id'],
            'account_to_id' => ['required', 'exists:accounts,id'],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:2048'],
        ]);

        // Belegnummer generieren
        $latest = Transaction::orderBy('id', 'desc')->first();
        $nextNumber = $latest ? $latest->id + 1 : 1;
        $receiptNumber = 'TRX-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $transaction = new Transaction();
        $transaction->tenant_id = auth()->user()->tenant_id;
        $transaction->date = $validated['date'];
        $transaction->description = $validated['description'];
        $transaction->amount = $validated['amount'];
        $transaction->account_from_id = $validated['account_from_id'];
        $transaction->account_to_id = $validated['account_to_id'];
        $transaction->receipt_number = $receiptNumber;

        if ($request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/receipts', $filename);
            $transaction->receipt_file = $filename;
        }

        $transaction->save();

        return redirect()->route('transactions.index')->with('success', 'Buchung erfolgreich gespeichert.');
    }

    public function cancel(Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);
        return view('transactions.cancel', compact('transaction'));
    }

    public function cancelStore(Request $request, Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        Transaction::create([
            'tenant_id' => $transaction->tenant_id,
            'date' => now()->format('Y-m-d'),
            'description' => 'Storno: ' . $transaction->description . ' – Grund: ' . $request->reason,
            'amount' => $transaction->amount,
            'account_from_id' => $transaction->account_to_id,
            'account_to_id' => $transaction->account_from_id,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Buchung wurde storniert.');
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorizeTransaction($transaction);
        abort(403, 'Buchungen dürfen nicht gelöscht werden.');
    }

    protected function authorizeTransaction(Transaction $transaction)
    {
        $user = auth()->user();

        Log::debug('🧪 authorizeTransaction', [
            'auth_check' => auth()->check(),
            'auth_user_id' => $user?->id,
            'auth_tenant_id' => $user?->tenant_id,
            'transaction_id' => $transaction->id,
            'transaction_tenant_id' => $transaction->tenant_id,
            'type_user_tenant_id' => gettype($user?->tenant_id),
            'type_transaction_tenant_id' => gettype($transaction->tenant_id),
            'ids_match_loose' => $user?->tenant_id == $transaction->tenant_id,
            'ids_match_strict' => $user?->tenant_id === $transaction->tenant_id,
        ]);

        if (!$user || $transaction->tenant_id != $user->tenant_id) {
            abort(403, 'Kein Zugriff auf diese Buchung.');
        }
    }

    public function summary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $start = $request->input('start', Carbon::now()->startOfYear()->format('Y-m-d'));
        $end = $request->input('end', Carbon::now()->endOfYear()->format('Y-m-d'));

        $transactions = Transaction::where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->with(['account_from', 'account_to'])
            ->orderBy('date')
            ->get();

        $income = $transactions->filter(fn($t) => optional($t->account_from)->type === 'einnahme')->sum('amount');
        $expense = $transactions->filter(fn($t) => optional($t->account_to)->type === 'ausgabe')->sum('amount');
        $saldo = $income - $expense;

        $byMonth = $transactions->groupBy(fn($t) => Carbon::parse($t->date)->format('Y-m'))->map(function ($items) {
            $income = $items->filter(fn($t) => optional($t->account_from)->type === 'einnahme')->sum('amount');
            $expense = $items->filter(fn($t) => optional($t->account_to)->type === 'ausgabe')->sum('amount');
            return ['income' => $income, 'expense' => $expense, 'saldo' => $income - $expense];
        });

        $currentMonthKey = Carbon::now()->format('Y-m');
        $previousMonthKey = Carbon::now()->subMonth()->format('Y-m');

        return view('transactions.summary', [
            'summary' => [
                'total_income' => $income,
                'total_expense' => $expense,
                'saldo' => $saldo,
                'by_month' => $byMonth,
                'transactions' => $transactions,
                'current' => $byMonth->get($currentMonthKey, ['income' => 0, 'expense' => 0, 'saldo' => 0]),
                'previous' => $byMonth->get($previousMonthKey, ['income' => 0, 'expense' => 0, 'saldo' => 0]),
            ],
            'start' => $start,
            'end' => $end,
        ]);
    }
}
