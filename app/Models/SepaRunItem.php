<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SepaRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sepa_run_id',
        'invoice_id',
        'member_id',
        'invoice_number',
        'member_name',
        'mandate_reference',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sepaRun()
    {
        return $this->belongsTo(SepaRun::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
