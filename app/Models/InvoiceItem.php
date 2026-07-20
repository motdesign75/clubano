<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'details',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'discount',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getTotalNetAttribute()
    {
        $net = $this->quantity * $this->unit_price;
        if ($this->discount) {
            $net -= ($net * $this->discount / 100);
        }
        return $net;
    }

    public function getTotalTaxAttribute()
    {
        return $this->total_net * ($this->tax_rate / 100);
    }

    public function getTotalGrossAttribute()
    {
        return $this->total_net + $this->total_tax;
    }
}
