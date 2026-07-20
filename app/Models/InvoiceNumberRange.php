<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceNumberRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'type',
        'prefix',
        'suffix',
        'start_number',
        'current_number',
        'reset_yearly',
    ];

    protected $casts = [
        'reset_yearly' => 'boolean',
    ];

    // Beziehungen
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Hilfsfunktion zur nächsten Rechnungsnummer
    public function generateNextNumber(): string
    {
        $this->current_number++;
        $this->save();

        return $this->prefix . str_pad($this->current_number, 4, '0', STR_PAD_LEFT) . $this->suffix;
    }
}
