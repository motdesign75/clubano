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

    protected $fillable = [
        'tenant_id',
        'uploaded_by',
        'title',
        'category',
        'status',
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
        'recognized_amount',
        'recognized_currency',
        'recognized_date',
        'recognized_vendor',
        'recognized_invoice_number',
        'recognition_source',
        'recognition_notes',
        'linked_transaction_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'document_date' => 'date',
        'expires_at' => 'date',
        'archived_at' => 'datetime',
        'is_booking_receipt' => 'boolean',
        'recognized_amount' => 'decimal:2',
        'recognized_date' => 'date',
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
            self::RECEIPT_NEEDS_REVIEW => 'Prüfen',
            self::RECEIPT_READY => 'Buchbar',
            self::RECEIPT_BOOKED => 'Gebucht',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
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
