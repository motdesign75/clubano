<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MemberCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'created_by',
        'description',
        'notes',
        'amount',
        'remaining_amount',
        'credited_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'remaining_amount' => 'float',
        'credited_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function (self $credit) {
            if (Auth::check()) {
                $credit->tenant_id ??= Auth::user()->tenant_id;
                $credit->created_by ??= Auth::id();
            }

            if ($credit->remaining_amount === null) {
                $credit->remaining_amount = $credit->amount;
            }
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(MemberCreditApplication::class)->latest('created_at');
    }

    public function isAvailable(): bool
    {
        return (float) $this->remaining_amount > 0.00001;
    }
}
