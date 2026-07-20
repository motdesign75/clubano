<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Transaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'date',
        'description',
        'amount',
        'account_from_id',
        'account_to_id',
    ];

    public function account_from()
    {
        return $this->belongsTo(Account::class, 'account_from_id');
    }

    public function account_to()
    {
        return $this->belongsTo(Account::class, 'account_to_id');
    }

    /**
     * Prüft, ob es sich um eine Einnahme handelt.
     * Einnahme: Geld kommt z. B. auf ein Einnahmenkonto oder von außen auf Bank/Kasse.
     */
    public function isIncome(): bool
    {
        return $this->account_to && $this->account_to->type === 'einnahme';
    }

    /**
     * Prüft, ob es sich um eine Ausgabe handelt.
     * Ausgabe: Geld wird von Bank/Kasse auf ein Ausgabenkonto überwiesen.
     */
    public function isExpense(): bool
    {
        return $this->account_from && $this->account_from->type === 'ausgabe';
    }
}
