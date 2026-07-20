<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'account_id',
        'amount',
        'payment_date',
        'note',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}