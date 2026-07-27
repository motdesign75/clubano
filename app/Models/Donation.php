<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Donation extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'transaction_id',
        'created_by',
        'certificate_number',
        'status',
        'kind',
        'donated_at',
        'amount',
        'purpose',
        'donor_name',
        'donor_email',
        'donor_street',
        'donor_zip',
        'donor_city',
        'payment_method',
        'notes',
        'certificate_issued_at',
        'sent_at',
    ];

    protected $casts = [
        'donated_at' => 'date',
        'amount' => 'decimal:2',
        'certificate_issued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function (Donation $donation) {
            if (Auth::check()) {
                $donation->tenant_id = $donation->tenant_id ?: Auth::user()->tenant_id;
                $donation->created_by = $donation->created_by ?: Auth::id();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ISSUED => 'Erstellt',
            self::STATUS_SENT => 'Versendet',
            self::STATUS_CANCELLED => 'Storniert',
            default => 'Entwurf',
        };
    }

    public function getDonorAddressAttribute(): string
    {
        return collect([
            $this->donor_street,
            trim(($this->donor_zip ?? '') . ' ' . ($this->donor_city ?? '')),
        ])->filter()->implode("\n");
    }

    public function issueCertificate(): void
    {
        if (!$this->certificate_number) {
            $this->certificate_number = $this->buildNextCertificateNumber();
        }

        if (!$this->certificate_issued_at) {
            $this->certificate_issued_at = now();
        }

        if ($this->status === self::STATUS_DRAFT) {
            $this->status = self::STATUS_ISSUED;
        }

        $this->save();
    }

    private function buildNextCertificateNumber(): string
    {
        $year = $this->donated_at?->format('Y') ?: now()->format('Y');
        $count = static::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->whereYear('donated_at', $year)
            ->whereNotNull('certificate_number')
            ->count() + 1;

        return 'SP-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
