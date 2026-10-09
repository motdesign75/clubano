<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Document extends Model
{
    public const CATEGORY_CLUB = 'verein';
    public const CATEGORY_MEMBERS = 'mitglieder';
    public const CATEGORY_FINANCE = 'finanzen';
    public const CATEGORY_CONTRACTS = 'vertraege';
    public const CATEGORY_PROTOCOLS = 'protokolle';
    public const CATEGORY_EVENTS = 'veranstaltungen';
    public const CATEGORY_PRIVACY = 'datenschutz';
    public const CATEGORY_OTHER = 'sonstiges';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVIEW = 'review';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ARCHIVED = 'archived';

    public const RECEIPT_NOT_RELEVANT = 'not_relevant';
    public const RECEIPT_NEEDS_REVIEW = 'needs_review';
    public const RECEIPT_READY = 'ready';
    public const RECEIPT_BOOKED = 'booked';

    public const PAYABLE_REVIEW = 'review';
    public const PAYABLE_OPEN = 'open';
    public const PAYABLE_PARTIAL = 'partial';
    public const PAYABLE_PAID = 'paid';
    public const PAYABLE_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'uploaded_by',
        'title',
        'category',
        'status',
        'folder_id',
        'description',
        'tags',
        'document_date',
        'expires_at',
        'archived_at',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'member_id',
        'project_id',
        'event_id',
        'protocol_id',
        'invoice_id',
        'is_booking_receipt',
        'receipt_status',
        'payable_status',
        'recognized_amount',
        'payable_paid_amount',
        'recognized_currency',
        'recognized_date',
        'payable_due_date',
        'payable_due_source',
        'payable_due_note',
        'recognized_vendor',
        'recognized_invoice_number',
        'payable_iban',
        'payable_reference',
        'recognition_source',
        'recognition_notes',
        'recognition_text',
        'recognition_quality',
        'recognition_fields',
        'linked_transaction_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'document_date' => 'date',
        'expires_at' => 'date',
        'archived_at' => 'datetime',
        'is_booking_receipt' => 'boolean',
        'recognized_amount' => 'decimal:2',
        'payable_paid_amount' => 'decimal:2',
        'recognized_date' => 'date',
        'payable_due_date' => 'date',
        'recognition_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function (Document $document) {
            if (Auth::check()) {
                $document->tenant_id ??= Auth::user()->tenant_id;
                $document->uploaded_by ??= Auth::id();
            }
        });
    }

    public static function categories(): array
    {
        return [
            self::CATEGORY_CLUB => 'Verein',
            self::CATEGORY_MEMBERS => 'Mitglieder',
            self::CATEGORY_FINANCE => 'Finanzen',
            self::CATEGORY_CONTRACTS => 'Verträge',
            self::CATEGORY_PROTOCOLS => 'Protokolle',
            self::CATEGORY_EVENTS => 'Veranstaltungen',
            self::CATEGORY_PRIVACY => 'Datenschutz',
            self::CATEGORY_OTHER => 'Sonstiges',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktiv',
            self::STATUS_REVIEW => 'Prüfung nötig',
            self::STATUS_EXPIRED => 'Abgelaufen',
            self::STATUS_ARCHIVED => 'Archiviert',
        ];
    }

    public static function receiptStatuses(): array
    {
        return [
            self::RECEIPT_NOT_RELEVANT => 'Kein Beleg',
            self::RECEIPT_NEEDS_REVIEW => 'Neu / prüfen',
            self::RECEIPT_READY => 'Noch nicht gebucht',
            self::RECEIPT_BOOKED => 'Gebucht',
        ];
    }

    public static function payableStatuses(): array
    {
        return [
            self::PAYABLE_REVIEW => 'Zu prüfen',
            self::PAYABLE_OPEN => 'Offen',
            self::PAYABLE_PARTIAL => 'Teilweise bezahlt',
            self::PAYABLE_PAID => 'Bezahlt',
            self::PAYABLE_CANCELLED => 'Storniert',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query
            ->whereNull('archived_at')
            ->where(function (Builder $query) {
                $query->where('status', self::STATUS_REVIEW)
                    ->orWhereDate('expires_at', '<=', now()->addDays(30));
            });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function protocol()
    {
        return $this->belongsTo(Protocol::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function folder()
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function linkedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'linked_transaction_id');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::categories()[$this->category] ?? 'Sonstiges';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? 'Aktiv';
    }

    public function getReceiptStatusLabelAttribute(): string
    {
        return self::receiptStatuses()[$this->receipt_status] ?? 'Kein Beleg';
    }

    public function getPayableStatusLabelAttribute(): string
    {
        return self::payableStatuses()[$this->payable_status] ?? $this->derivedPayableStatusLabel();
    }

    public function getPayablePaidAmountAttribute($value): float
    {
        if ($value !== null) {
            return round((float) $value, 2);
        }

        return $this->linkedTransaction ? round((float) $this->linkedTransaction->amount, 2) : 0.0;
    }

    public function payableRemainingAmount(): float
    {
        $amount = filled($this->recognized_amount) ? (float) $this->recognized_amount : 0.0;

        return round(max(0, $amount - (float) $this->payable_paid_amount), 2);
    }

    public function derivedPayableStatus(): string
    {
        if (! $this->is_booking_receipt) {
            return self::PAYABLE_REVIEW;
        }

        if ($this->payable_status === self::PAYABLE_CANCELLED) {
            return self::PAYABLE_CANCELLED;
        }

        if ($this->payable_status === self::PAYABLE_PAID || (filled($this->recognized_amount) && $this->payableRemainingAmount() <= 0.009)) {
            return self::PAYABLE_PAID;
        }

        if ((float) $this->payable_paid_amount > 0) {
            return self::PAYABLE_PARTIAL;
        }

        if ($this->receipt_status === self::RECEIPT_READY && filled($this->recognized_amount)) {
            return self::PAYABLE_OPEN;
        }

        return self::PAYABLE_REVIEW;
    }

    public function derivedPayableStatusLabel(): string
    {
        return self::payableStatuses()[$this->derivedPayableStatus()] ?? 'Zu prüfen';
    }

    public function isPayableOverdue(): bool
    {
        return $this->payable_due_date
            && $this->payable_due_date->isPast()
            && ! in_array($this->derivedPayableStatus(), [self::PAYABLE_PAID, self::PAYABLE_CANCELLED], true);
    }

    public function getHumanSizeAttribute(): string
    {
        if ($this->size >= 1048576) {
            return number_format($this->size / 1048576, 1, ',', '.') . ' MB';
        }

        return number_format(max(1, $this->size / 1024), 0, ',', '.') . ' KB';
    }

    public function getLinkedContextAttribute(): ?string
    {
        return $this->member?->full_name
            ?? $this->project?->name
            ?? $this->event?->title
            ?? $this->protocol?->title
            ?? $this->invoice?->invoice_number;
    }
}
