<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Formular anzeigen
    |--------------------------------------------------------------------------
    */

    public function create(Invoice $invoice)
    {
        abort_if(
            $invoice->tenant_id !== auth()->user()->tenant_id,
            403
        );

        $invoice->loadMissing(['items', 'eventBookings', 'incomeAccount']);

        $accounts = Account::where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('type', ['bank', 'kasse'])
            ->get();

        $incomeAccounts = Account::where('tenant_id', auth()->user()->tenant_id)
            ->where('type', 'einnahme')
            ->where('active', true)
            ->orderBy('number')
            ->orderBy('name')
            ->get();

        if ($invoice->incomeAccount) {
            $suggestedIncomeAccount = $invoice->incomeAccount;
            $incomeAccountHint = 'Dieses Ertragskonto ist bereits auf der Rechnung hinterlegt und wird automatisch fuer die Buchung verwendet.';
        } else {
            [$suggestedIncomeAccount, $incomeAccountHint] = $this->suggestIncomeAccount($invoice, $incomeAccounts);
        }

        return view('payments.create', compact(
            'invoice',
            'accounts',
            'incomeAccounts',
            'suggestedIncomeAccount',
            'incomeAccountHint'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | Zahlung speichern
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, Invoice $invoice)
    {
        $tenantId = auth()->user()->tenant_id;

        abort_if(
            $invoice->tenant_id !== $tenantId,
            403
        );

        $invoice->loadMissing(['items', 'eventBookings', 'incomeAccount']);

        $validated = $request->validate([
            'account_id'        => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'income_account_id' => [$invoice->income_account_id ? 'nullable' : 'required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'amount'            => 'required|numeric|min:0.01',
            'payment_date'      => 'required|date',
            'note'              => 'nullable|string|max:255',
        ]);

        $account = Account::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['account_id']);

        $incomeAccountId = $invoice->income_account_id ?: $validated['income_account_id'];

        $incomeAccount = Account::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'einnahme')
            ->findOrFail($incomeAccountId);

        if (!$invoice->income_account_id) {
            $invoice->forceFill(['income_account_id' => $incomeAccount->id])->save();
        }


        /*
        |--------------------------------------------------------------------------
        | Payment speichern
        |--------------------------------------------------------------------------
        */

        Payment::create([

            'tenant_id'    => $tenantId,
            'invoice_id'   => $invoice->id,
            'account_id'   => $account->id,

            'amount'       => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'note'         => $validated['note'],

        ]);

        /*
        |--------------------------------------------------------------------------
        | Transaction erzeugen
        |--------------------------------------------------------------------------
        */

        $transaction = Transaction::create([

            'tenant_id' => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'status' => 'abgeschlossen',
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),

            // Einnahme -> Bank/Kasse
            'account_from_id' => $incomeAccount->id,
            'account_to_id'   => $account->id,

            'amount' => $validated['amount'],
            'tax_area' => $incomeAccount->tax_area ?: 'ideell',

            'date' => $validated['payment_date'],

            'description' => $this->buildPaymentTransactionDescription($invoice),

        ]);


/*
|--------------------------------------------------------------------------
| Kontostände neu berechnen
|--------------------------------------------------------------------------
*/

$fromAccount = \App\Models\Account::find($incomeAccount->id);
$toAccount   = \App\Models\Account::where('tenant_id', $tenantId)->find($account->id);

if ($fromAccount) {
    $fromAccount->updateBalance();
}

if ($toAccount) {
    $toAccount->updateBalance();
}
        /*
        |--------------------------------------------------------------------------
        | Rechnung bezahlt?
        |--------------------------------------------------------------------------
        */

        $paid = $invoice->payments()->sum('amount');

        if ($paid >= $invoice->getTotal()) {

            $invoice->markAsPaid();

        }


        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Zahlung gebucht');

    }

    private function suggestIncomeAccount(Invoice $invoice, Collection $incomeAccounts): array
    {
        if ($incomeAccounts->isEmpty()) {
            return [null, 'Bitte legt zuerst mindestens ein aktives Einnahmekonto an.'];
        }

        if ($this->looksLikeMembershipInvoice($invoice)) {
            return [
                $this->findIncomeAccountByNames($incomeAccounts, ['Mitgliederbeiträge', 'Mitgliedsbeitraege']) ?? $incomeAccounts->first(),
                'Vorauswahl: Mitgliedsbeitrag. Falls diese Rechnung kein Beitrag ist, bitte das Ertragskonto anpassen.',
            ];
        }

        if ($invoice->eventBookings()->exists()) {
            return [
                $this->findIncomeAccountByNames($incomeAccounts, ['Veranstaltungen', 'Veranstaltungserlöse', 'Veranstaltungserloese', 'Teilnahmegebühren', 'Teilnahmegebuehren', 'Event-Einnahmen']) ?? $incomeAccounts->first(),
                'Vorauswahl: Veranstaltung bzw. kostenpflichtige Buchung.',
            ];
        }

        return [
            $this->findIncomeAccountByNames($incomeAccounts, ['Sonstige Erlöse', 'Sonstige Erloese', 'Erlöse', 'Erloese', 'Dienstleistungen']) ?? $incomeAccounts->first(),
            'Bitte das passende Ertragskonto fuer diese Rechnung pruefen, bevor die Zahlung gespeichert wird.',
        ];
    }

    private function findIncomeAccountByNames(Collection $incomeAccounts, array $names): ?Account
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

    private function looksLikeMembershipInvoice(Invoice $invoice): bool
    {
        if ($invoice->period_from || $invoice->period_to) {
            return true;
        }

        $firstDescription = mb_strtolower((string) optional($invoice->items->first())->description);

        return str_contains($firstDescription, 'mitgliedsbeitrag');
    }

    private function buildPaymentTransactionDescription(Invoice $invoice): string
    {
        return 'Zahlung ' . $invoice->getDocumentLabel() . ' ' . $invoice->invoice_number;
    }
}
