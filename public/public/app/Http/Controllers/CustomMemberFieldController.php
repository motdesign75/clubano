<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomMemberField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomMemberFieldController extends Controller
{
    /**
     * Zeigt die Übersicht aller benutzerdefinierten Felder des aktuellen Vereins.
     */
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        $fields = CustomMemberField::where('tenant_id', $tenantId)->orderBy('order')->get();

        return view('custom_fields.index', compact('fields'));
    }

    /**
     * Formular zur Erstellung eines neuen Feldes.
     */
    public function create()
    {
        return view('custom_fields.create');
    }

    /**
     * Speichert ein neues benutzerdefiniertes Feld.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|alpha_dash',
            'label' => 'nullable|string|max:255',
            'type' => 'required|in:text,number,date,email,textarea,select',
            'options' => 'nullable|string',
        ]);

        $validated['label'] = $validated['label'] ?? $validated['name'];
        $validated['slug'] = Str::slug($validated['name']);
        $validated['tenant_id'] = Auth::user()->tenant_id;
        $validated['required'] = $request->has('required');
        $validated['visible'] = $request->has('visible');
        $validated['order'] = (CustomMemberField::where('tenant_id', $validated['tenant_id'])->max('order') ?? 0) + 1;

        CustomMemberField::create($validated);

        return redirect()->route('custom-fields.index')->with('success', 'Feld wurde erfolgreich hinzugefügt.');
    }

    /**
     * Formular zur Bearbeitung eines bestehenden Feldes.
     */
    public function edit(CustomMemberField $customMemberField)
    {
        $this->authorizeField($customMemberField);

        return view('custom_fields.edit', compact('customMemberField'));
    }

    /**
     * Aktualisiert ein bestehendes Feld.
     */
    public function update(Request $request, CustomMemberField $customMemberField)
    {
        $this->authorizeField($customMemberField);

        $validated = $request->validate([
            'name' => 'required|string|max:255|alpha_dash',
            'label' => 'nullable|string|max:255',
            'type' => 'required|in:text,number,date,email,textarea,select',
            'options' => 'nullable|string',
        ]);

        $validated['label'] = $validated['label'] ?? $validated['name'];
        $validated['slug'] = Str::slug($validated['name']);
        $validated['required'] = $request->has('required');
        $validated['visible'] = $request->has('visible');

        $customMemberField->update($validated);

        return redirect()->route('custom-fields.index')->with('success', 'Feld wurde aktualisiert.');
    }

    /**
     * Löscht ein Feld.
     */
    public function destroy(CustomMemberField $customMemberField)
    {
        $this->authorizeField($customMemberField);

        $customMemberField->delete();

        return redirect()->route('custom-fields.index')->with('success', 'Feld wurde gelöscht.');
    }

    /**
     * Autorisiert den Zugriff auf das Feld basierend auf tenant_id.
     */
    private function authorizeField(CustomMemberField $field)
    {
        if ($field->tenant_id !== Auth::user()->tenant_id) {
            abort(403, 'Unberechtigter Zugriff');
        }
    }
}
