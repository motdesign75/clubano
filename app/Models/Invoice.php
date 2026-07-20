<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'document_type',
        'member_id',
        'contact_id',
        'income_account_id',
        'recipient_type',
        'recipient_name',
        'recipient_company',
        'recipient_salutation',
        'recipient_email',
        'recipient_street',
        'recipient_zip',
        'recipient_city',
        'recipient_country',
        'intro_text',
        'payment_text',
        'closing_text',
        'invoice_number',
        'invoice_date',
        'due_date',
        'period_year',
        'period_from',
        'period_to',
        'status',
        'paid_at',
        'sepa_exported_at',
        'sepa_sequence_type',
        'last_sepa_run_id',
        'discount',
        'tax_rate',
        'total',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'paid_at' => 'datetime',
        'sepa_exported_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function incomeAccount()
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function eventBookings()
    {
        return $this->hasMany(EventBooking::class);
    }

    public function lastSepaRun()
    {
        return $this->belongsTo(SepaRun::class, 'last_sepa_run_id');
    }

    public static function generateInvoiceNumber(?int $tenantId = null): string
    {
        return static::generateDocumentNumber('invoice', $tenantId);
    }

    public static function generateDocumentNumber(string $documentType = 'invoice', ?int $tenantId = null): string
    {
        $tenantId ??= auth()->user()?->tenant_id;

        if (!$tenantId) {
            return ($documentType === 'offer' ? 'A-' : 'R-') . now()->format('YmdHis');
        }

        $rangeType = $documentType === 'offer' ? 'angebot' : 'beitrag';

        $range = \App\Models\InvoiceNumberRange::where('tenant_id', $tenantId)
            ->where('type', $rangeType)
            ->first();

        if (!$range) {
            return ($documentType === 'offer' ? 'A-' : 'R-') . now()->format('YmdHis');
        }

        $range->current_number++;
        $range->save();

        return $range->prefix
            . str_pad($range->current_number, 4, '0', STR_PAD_LEFT)
            . $range->suffix;
    }

    public function getSubtotal(): float
    {
        return $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);
    }

    public function getDiscountAmount(): float
    {
        $discount = $this->discount ?? 0;

        return round(($this->getSubtotal() * $discount) / 100, 2);
    }

    public function getNetTotal(): float
    {
        return round($this->getSubtotal() - $this->getDiscountAmount(), 2);
    }

    public function getTaxAmount(): float
    {
        $tax = $this->tax_rate ?? 0;

        return round(($this->getNetTotal() * $tax) / 100, 2);
    }

    public function getTotal(): float
    {
        return round($this->getNetTotal() + $this->getTaxAmount(), 2);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOffer(): bool
    {
        return $this->document_type === 'offer';
    }

    public function isInvoice(): bool
    {
        return !$this->isOffer();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isOverdue(): bool
    {
        return $this->isInvoice()
            && $this->isOpen()
            && $this->due_date !== null
            && $this->due_date->isBefore(now()->startOfDay());
    }

    public function overdueDays(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(now()->startOfDay());
    }

    public function isDraft(): bool
    {
        return $this->status === 'entwurf';
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();

        $this->eventBookings()->update(['payment_status' => 'paid']);
    }

    public function wasSepaExported(): bool
    {
        return $this->sepa_exported_at !== null;
    }

    public function getRecipientDisplayName(): string
    {
        if ($this->recipient_company && $this->recipient_name) {
            return $this->recipient_company . ' - ' . $this->recipient_name;
        }

        if ($this->recipient_company) {
            return $this->recipient_company;
        }

        return $this->recipient_name ?: 'Unbekannter Empfaenger';
    }

    public function getRecipientAddressLines(): array
    {
        $lines = array_filter([
            $this->recipient_name,
            $this->recipient_company,
            $this->recipient_street,
            trim(($this->recipient_zip ?? '') . ' ' . ($this->recipient_city ?? '')),
        ]);

        if ($this->recipient_country && strtoupper($this->recipient_country) !== 'DE') {
            $lines[] = $this->recipient_country;
        }

        return array_values($lines);
    }

    public function getDocumentLabel(): string
    {
        return $this->isOffer() ? 'Angebot' : 'Rechnung';
    }

    public function getDocumentLabelPlural(): string
    {
        return $this->isOffer() ? 'Angebote' : 'Rechnungen';
    }
}
