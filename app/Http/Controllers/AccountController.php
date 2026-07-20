<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'bank');

        $balanceAccounts = Account::forCurrentTenant()
            ->where('active', true)
            ->whereIn('type', ['bank', 'kasse'])
            ->orderBy('number')
            ->get();

        $chartAccounts = Account::forCurrentTenant()
            ->where('active', true)
            ->whereIn('type', ['einnahme', 'ausgabe'])
            ->orderBy('number')
            ->get();

        $inactiveAccounts = Account::forCurrentTenant()
            ->where('active', false)
            ->orderBy('number')
            ->get();

        return view('accounts.index', compact('balanceAccounts', 'chartAccounts', 'inactiveAccounts', 'tab'));
    }

    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        // Checkbox-Werte vorbereiten
        $request->merge([
            'active'   => $request->has('active'),
            'online'   => $request->has('online'),
        ]);

        $validated = $request->validate([
            'number'         => ['nullable', 'string', 'max:20'],
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:bank,kasse,einnahme,ausgabe'],
            'iban'           => ['nullable', 'string', 'max:34'],
            'bic'            => ['nullable', 'string', 'max:11'],
            'description'    => ['nullable', 'string'],
            'balance_start'  => ['nullable', 'numeric'],
            'balance_date'   => ['nullable', 'date'],
            'tax_area'       => ['nullable', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'active'         => ['required', 'boolean'],
            'online'         => ['required', 'boolean'],
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;

        $account = Account::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konto erfolgreich erstellt.',
                'account' => $account,
            ]);
        }

        return redirect()->route('accounts.index')->with('success', 'Konto erfolgreich erstellt.');
    }

    public function edit(Account $account)
    {
        $this->authorizeAccount($account);

        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
        $this->authorizeAccount($account);

        // Checkbox-Werte vorbereiten
        $request->merge([
            'active'   => $request->has('active'),
            'online'   => $request->has('online'),
        ]);

        $validated = $request->validate([
            'number'         => ['nullable', 'string', 'max:20'],
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:bank,kasse,einnahme,ausgabe'],
            'iban'           => ['nullable', 'string', 'max:34'],
            'bic'            => ['nullable', 'string', 'max:11'],
            'description'    => ['nullable', 'string'],
            'balance_start'  => ['nullable', 'numeric'],
            'balance_date'   => ['nullable', 'date'],
            'tax_area'       => ['nullable', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'active'         => ['required', 'boolean'],
            'online'         => ['required', 'boolean'],
        ]);

        $account->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konto erfolgreich aktualisiert.',
                'account' => $account,
            ]);
        }

        return redirect()->route('accounts.index')->with('success', 'Konto erfolgreich aktualisiert.');
    }

    public function destroy(Account $account)
    {
        $this->authorizeAccount($account);
        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Konto gelöscht.');
    }

    protected function authorizeAccount(Account $account)
    {
        if ((string) $account->tenant_id !== (string) auth()->user()->tenant_id) {
            abort(403, 'Kein Zugriff auf dieses Konto.');
        }
    }
}
