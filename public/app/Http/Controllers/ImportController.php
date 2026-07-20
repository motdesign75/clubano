<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Member;
use Carbon\Carbon;

class ImportController extends Controller
{
    /**
     * Zeigt das Upload-Formular für die CSV-Datei.
     */
    public function showUploadForm()
    {
        return view('import.mitglieder.upload');
    }

    /**
     * Zeigt eine Vorschau der CSV-Datei und ermöglicht die Feldzuordnung.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->store('temp');

        $file = Storage::get($path);
        $lines = array_map('str_getcsv', explode("\n", $file));
        $headers = $lines[0] ?? [];
        $rows = array_slice($lines, 1, 5); // Vorschau: max. 5 Zeilen

        return view('import.mitglieder.preview', compact('headers', 'rows', 'path'));
    }

    /**
     * Führt den Import der Mitglieder auf Basis des Mappings durch.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'mapping' => 'required|array',
        ]);

        $file = Storage::get($request->input('path'));
        $lines = array_map('str_getcsv', explode("\n", $file));

        $headers = $lines[0] ?? [];
        $rows = array_slice($lines, 1);

        $allowedSalutations = ['Herr', 'Frau', 'Divers', 'Liebe', 'Lieber', 'Hallo'];

        foreach ($rows as $row) {
            if (!is_array($row) || count($row) === 0 || empty(trim($row[0]))) {
                continue; // Leere oder ungültige Zeilen überspringen
            }

            $data = [];

            foreach ($request->input('mapping') as $index => $field) {
                if ($field !== 'skip' && isset($row[$index])) {
                    $value = trim($row[$index]);

                    // Anrede prüfen
                    if ($field === 'salutation') {
                        if ($value === '' || !in_array($value, $allowedSalutations)) {
                            continue;
                        }
                    }

                    // Geburtstag formatieren (von z. B. 04.04.1971 zu 1971-04-04)
                    if ($field === 'birthday') {
                        try {
                            $value = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
                        } catch (\Exception $e) {
                            $value = null; // Fehlerhafte Daten ignorieren
                        }
                    }

                    $data[$field] = $value;
                }
            }

            // Mandanten-ID setzen
            $data['tenant_id'] = auth()->user()->tenant_id ?? null;

            // Nur speichern, wenn sinnvoll
            if (!empty($data['first_name']) || !empty($data['last_name'])) {
                Member::create($data);
            }
        }

        return redirect()
            ->route('members.index')
            ->with('success', 'Mitglieder erfolgreich importiert.');
    }
}
