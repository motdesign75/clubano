<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function show($filename)
    {
        // Sicherheitscheck: keine Pfadmanipulation erlauben
        $filename = basename($filename);

        // Absoluter Pfad im Dateisystem
        $storagePath = storage_path('app/public/receipts/' . $filename);

        // Prüfen, ob Datei wirklich existiert
        if (!file_exists($storagePath)) {
            abort(404, 'Beleg nicht gefunden.');
        }

        // Datei anzeigen (Inline im Browser)
        return response()->file($storagePath);
    }
}
