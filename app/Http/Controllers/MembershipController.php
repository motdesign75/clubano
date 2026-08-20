<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function index()
    {
        $memberships = Membership::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return view('memberships.index', compact('memberships'));
    }

    public function create()
    {
        return view('memberships.create');
    }

    public function store(Request $request)
    {
        $this->normalizeAmount($request);
        $this->normalizeDecimalField($request, 'admission_fee');
        $this->normalizeInterval($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'admission_fee' => 'nullable|numeric|min:0',
            'interval' => 'required|in:monatlich,vierteljaehrlich,halbjaehrlich,jaehrlich,vierteljährlich,halbjährlich,jährlich',
        ]);

        $validated['interval'] = $this->canonicalInterval($validated['interval']);
        $validated['tenant_id'] = auth()->user()->tenant_id;

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

        $this->normalizeAmount($request);
        $this->normalizeDecimalField($request, 'admission_fee');
        $this->normalizeInterval($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'admission_fee' => 'nullable|numeric|min:0',
            'interval' => 'required|in:monatlich,vierteljaehrlich,halbjaehrlich,jaehrlich,vierteljährlich,halbjährlich,jährlich',
        ]);

        $validated['interval'] = $this->canonicalInterval($validated['interval']);
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
     * Schutz vor Zugriff auf fremde Daten.
     */
    private function authorizeTenant(Membership $membership)
    {
        if ($membership->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unberechtigter Zugriff.');
        }
    }

    private function normalizeAmount(Request $request): void
    {
        $this->normalizeDecimalField($request, 'amount');
    }

    private function normalizeDecimalField(Request $request, string $field): void
    {
        $amount = $request->input($field);

        if ($amount === null) {
            return;
        }

        $normalized = trim((string) $amount);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        $request->merge([
            $field => $normalized,
        ]);
    }

    private function normalizeInterval(Request $request): void
    {
        $interval = $request->input('interval');

        if ($interval === null) {
            return;
        }

        $request->merge([
            'interval' => $this->canonicalInterval((string) $interval),
        ]);
    }

    private function canonicalInterval(string $interval): string
    {
        return match (trim(mb_strtolower($interval))) {
            'monatlich' => 'monatlich',
            'vierteljaehrlich', 'vierteljährlich', 'quartal', 'quartalsweise' => 'vierteljährlich',
            'halbjaehrlich', 'halbjährlich', 'halbjahr' => 'halbjährlich',
            'jaehrlich', 'jährlich', 'jahr' => 'jährlich',
            default => trim($interval),
        };
    }
}
