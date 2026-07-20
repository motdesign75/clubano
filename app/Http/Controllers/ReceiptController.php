<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * Zeigt einen Beleg im Browser an.
     *
     * @param string $path
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function show(string $path)
    {
        if (str_contains($path, '..')) {
            abort(403, 'Ungültiger Pfad.');
        }

        $transaction = Transaction::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('receipt_file', $path)
            ->first();

        if (!$transaction) {
            abort(404, 'Beleg nicht gefunden.');
        }

        $storagePath = storage_path('app/public/' . $transaction->receipt_file);

        if (!file_exists($storagePath)) {
            abort(404, 'Beleg nicht gefunden.');
        }

        return response()->file($storagePath);
    }
}
