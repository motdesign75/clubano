<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'tenant_id',
        'code',
        'title',
        'original_amount',
        'remaining_amount',
        'currency',
        'issued_at',
        'expires_at',
        'status',
        'buyer_name',
        'recipient_name',
        'legacy',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'issued_at' => 'date',
        'expires_at' => 'date',
        'legacy' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function redemptions()
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_REDEEMED => 'Eingelöst',
            self::STATUS_EXPIRED => 'Abgelaufen',
            self::STATUS_VOID => 'Gesperrt',
            default => 'Aktiv',
        };
    }

    public function getIsRedeemableAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && (float) $this->remaining_amount > 0
            && (! $this->expires_at || $this->expires_at->endOfDay()->isFuture());
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Z0-9-]/i', '', trim($code)) ?? '');
    }

    public static function nextCode(int $tenantId, ?string $prefix = null): string
    {
        $prefix = self::normalizeCode($prefix ?: 'CLB-' . now()->format('Y'));

        do {
            $code = $prefix . '-' . Str::upper(Str::random(6));
        } while (self::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }
}
