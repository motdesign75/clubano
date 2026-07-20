<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::forCurrentTenant()->orderBy('number')->get();

        return view('accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'number' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:bank,kasse,einnahme,ausgabe'],
            'online' => ['nullable', 'boolean'],
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['online'] = $request->has('online');

        Account::create($validated);

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

        $validated = $request->validate([
            'number' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:bank,kasse,einnahme,ausgabe'],
            'online' => ['nullable', 'boolean'],
        ]);

        $validated['online'] = $request->has('online');

        $account->update($validated);

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
    ([
        'account_id' => $account->id,
        'account_tenant_id' => $account->tenant_id,
        'user_tenant_id' => auth()->user()->tenant_id,
    ]);

    if ((string) $account->tenant_id !== (string) auth()->user()->tenant_id) {
    abort(403, 'Kein Zugriff auf dieses Konto.');
    }
}

}
