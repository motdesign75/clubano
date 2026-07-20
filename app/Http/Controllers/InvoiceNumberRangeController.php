<?php

namespace App\Http\Controllers;

use App\Models\InvoiceNumberRange;
use Illuminate\Http\Request;

class InvoiceNumberRangeController extends Controller
{
    // Liste aller Nummernkreise des aktuellen Vereins (Mandant)
    public function index()
    {
        $ranges = InvoiceNumberRange::where('tenant_id', auth()->user()->tenant_id)->get();

        return view('number_ranges.index', compact('ranges'));
    }

    // Formular zum Anlegen
    public function create()
    {
        return view('number_ranges.create');
    }

    // Speichern eines neuen Nummernkreises
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|max:50',
            'prefix' => 'nullable|string|max:50',
            'suffix' => 'nullable|string|max:50',
            'start_number' => 'required|integer|min:1',
            'reset_yearly' => 'sometimes|boolean', // ✅ hier korrekt gesetzt
        ]);

        InvoiceNumberRange::create([
            'tenant_id' => auth()->user()->tenant_id,
            'type' => $request->type,
            'prefix' => $request->prefix,
            'suffix' => $request->suffix,
            'start_number' => $request->start_number,
            'current_number' => $request->start_number - 1,
            'reset_yearly' => $request->boolean('reset_yearly', false),
        ]);

        return redirect()->route('number_ranges.index')->with('success', 'Nummernkreis wurde gespeichert.');
    }

    // Formular zum Bearbeiten
    public function edit(InvoiceNumberRange $number_range)
    {
        $this->authorizeAccess($number_range);

        return view('number_ranges.edit', compact('number_range'));
    }

    // Aktualisieren eines bestehenden Nummernkreises
    public function update(Request $request, InvoiceNumberRange $number_range)
    {
        $this->authorizeAccess($number_range);

        $request->validate([
            'prefix' => 'nullable|string|max:50',
            'suffix' => 'nullable|string|max:50',
            'reset_yearly' => 'sometimes|boolean',
        ]);

        $number_range->update([
            'prefix' => $request->prefix,
            'suffix' => $request->suffix,
            'reset_yearly' => $request->boolean('reset_yearly', false),
        ]);

        return redirect()->route('number_ranges.index')->with('success', 'Änderungen gespeichert.');
    }

    // Zugriffsschutz: Nur Zugriff auf eigene Nummernkreise
    private function authorizeAccess(InvoiceNumberRange $range)
    {
        abort_if($range->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
