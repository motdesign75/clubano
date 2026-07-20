<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MemberCreditApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'member_credit_id',
        'invoice_id',
        'amount',
        'applied_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'applied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function (self $application) {
            if (Auth::check()) {
                $application->tenant_id ??= Auth::user()->tenant_id;
            }
        });
    }

    public function credit()
    {
        return $this->belongsTo(MemberCredit::class, 'member_credit_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
