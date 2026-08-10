<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Scopes\CurrentTenantScope;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'name',
        'type',
        'tax_area',
        'chart_name',
        'tax_key',
        'is_postable',
        'datev_automatic',
        'import_source',
        'iban',
        'bic',
        'description',
        'active',
        'online',
        'balance_start',
        'balance_date',
        'balance_current',
        'tenant_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'online' => 'boolean',
        'is_postable' => 'boolean',
        'datev_automatic' => 'boolean',
        'balance_start' => 'float',
        'balance_current' => 'float',
        'balance_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($account) {
            if (Auth::check() && blank($account->tenant_id)) {
                $account->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    // Buchungen, bei denen dieses Konto als "von" verwendet wurde (Ausgabe)
    public function transactionsFrom()
    {
        return $this->hasMany(Transaction::class, 'account_from_id');
    }

    // Buchungen, bei denen dieses Konto als "an" verwendet wurde (Einnahme)
    public function transactionsTo()
    {
        return $this->hasMany(Transaction::class, 'account_to_id');
    }

    // ✅ NEU: Methode zur Berechnung des aktuellen Saldos
    public function updateBalance(): void
    {
        $sumIn = $this->transactionsTo()
            ->where('status', 'abgeschlossen')
            ->sum('amount');

        $sumOut = $this->transactionsFrom()
            ->where('status', 'abgeschlossen')
            ->sum('amount');

        $this->balance_current = ($this->balance_start ?? 0) + $sumIn - $sumOut;
        $this->save();
    }

    public function getTaxAreaLabelAttribute(): string
    {
        return match ($this->tax_area) {
            'ideell' => 'Ideeller Bereich',
            'zweckbetrieb' => 'Zweckbetrieb',
            'vermoegensverwaltung' => 'Vermögensverwaltung',
            'wirtschaftlich' => 'Wirtschaftlicher Geschäftsbetrieb',
            default => 'Unbekannt',
        };
    }
}
