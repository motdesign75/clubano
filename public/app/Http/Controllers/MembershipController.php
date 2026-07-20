<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MembershipController extends Controller
{
    public function index()
    {
        $memberships = Membership::where('tenant_id', auth()->user()->tenant_id)->get();

        return view('memberships.index', compact('memberships'));
    }

    public function create()
    {
        return view('memberships.create');
    }

    public function store(Request $request)
    {
        // DEBUG: Fee umwandeln (Komma → Punkt) für Validierung
        $request->merge([
            'fee' => str_replace(',', '.', $request->input('fee'))
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fee' => 'nullable|numeric|min:0',
            'billing_cycle' => 'required|in:monatlich,quartalsweise,halbjährlich,jährlich',
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;

        // DEBUG: Logge Eingaben zur Kontrolle – später auskommentieren
        Log::debug('Membership Store Request Input:', $request->all());
        Log::debug('Membership Validated Data:', $validated);

        Membership::create($validated);

        return redirect()->route('memberships.index')->with('success', 'Mitgliedschaft erstellt.');
    }

    public function edit(Membership $membership)
    {
        $this->authorizeTenant($membership);

        return view('memberships.edit', compact('membership'));
    }

    public function update(Request $request, Membership $membership)
    {
        $this->authorizeTenant($membership);

        // DEBUG: Fee umwandeln (Komma → Punkt)
        $request->merge([
            'fee' => str_replace(',', '.', $request->input('fee'))
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fee' => 'nullable|numeric|min:0',
            'billing_cycle' => 'required|in:monatlich,quartalsweise,halbjährlich,jährlich',
        ]);

        // DEBUG: Logge Daten zur Kontrolle – später auskommentieren
        Log::debug('Membership Update Request Input:', $request->all());
        Log::debug('Membership Validated Data (Update):', $validated);

        $membership->update($validated);

        return redirect()->route('memberships.index')->with('success', 'Mitgliedschaft aktualisiert.');
    }

    public function destroy(Membership $membership)
    {
        $this->authorizeTenant($membership);

        $membership->delete();

        return redirect()->route('memberships.index')->with('success', 'Mitgliedschaft gelöscht.');
    }

    /**
     * Schützt die Daten des aktuellen Vereins.
     */
    private function authorizeTenant(Membership $membership)
    {
        if ($membership->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unberechtigter Zugriff.');
        }
    }
}
