<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\CurrentTenantScope;

class Transaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by',
        'updated_by',
        'date',
        'description',
        'amount',
        'account_from_id',
        'account_to_id',
        'tax_area',
        'receipt_number',
        'receipt_file',
        'receipt_kind',
        'receipt_meta',
        'invoice_id',
        'status',
        'finalized_at',
        'finalized_by',
        'journal_reviewed_at',
        'journal_reviewed_by',
        'journal_receipt_checked_at',
        'journal_receipt_checked_by',
    ];

    /**
     * 🔥 WICHTIG: Typumwandlungen
     */
    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'finalized_at' => 'datetime',
        'journal_reviewed_at' => 'datetime',
        'journal_receipt_checked_at' => 'datetime',
        'receipt_meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($transaction) {
            if (Auth::check() && blank($transaction->tenant_id)) {
                $transaction->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Beziehung: Konto (von)
     */
    public function account_from()
    {
        return $this->belongsTo(Account::class, 'account_from_id');
    }

    /**
     * Beziehung: Konto (nach)
     */
    public function account_to()
    {
        return $this->belongsTo(Account::class, 'account_to_id');
    }

    /**
     * Beziehung: Transaktion gehört zu einem Verein (Mandant)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function journalReviewer()
    {
        return $this->belongsTo(User::class, 'journal_reviewed_by');
    }

    public function journalReceiptChecker()
    {
        return $this->belongsTo(User::class, 'journal_receipt_checked_by');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Scope: Filter für aktuellen Mandanten
     */
    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    /**
     * Prüft, ob es sich um eine Einnahme handelt
     */
    public function isIncome(): bool
    {
        return $this->account_to && $this->account_to->type === 'einnahme';
    }

    /**
     * Prüft, ob es sich um eine Ausgabe handelt
     */
    public function isExpense(): bool
    {
        return $this->account_from && $this->account_from->type === 'ausgabe';
    }

    public function isDraft(): bool
    {
        return $this->status === 'entwurf';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'abgeschlossen';
    }

    public function isCancelled(): bool
    {
        return str_starts_with((string) $this->description, 'Storno zu ') || str_starts_with((string) $this->description, 'Storno:');
    }

    public function hasSystemReceipt(): bool
    {
        return ! empty($this->invoice_id)
            || $this->receipt_kind === 'system_invoice'
            || str_starts_with((string) $this->description, 'Zahlung Rechnung ')
            || str_starts_with((string) $this->description, 'Zahlung Angebot ');
    }

    public function hasOwnReceipt(): bool
    {
        return $this->receipt_kind === 'eigenbeleg'
            || str_contains((string) $this->receipt_file, '/eigenbelege/')
            || str_starts_with((string) $this->receipt_file, 'receipts/' . $this->tenant_id . '/eigenbelege/');
    }

    public function hasContractReceipt(): bool
    {
        return $this->receipt_kind === 'vertrag';
    }

    public function hasDocumentReceipt(): bool
    {
        return $this->receipt_kind === 'document'
            && ! empty($this->receipt_meta['document_id'] ?? null);
    }

    public function hasAnyReceipt(): bool
    {
        return !empty($this->receipt_file) || $this->hasSystemReceipt() || $this->hasContractReceipt() || $this->hasDocumentReceipt();
    }

    public function receiptEvidenceLabel(): string
    {
        if ($this->hasOwnReceipt()) {
            return 'Eigenbeleg';
        }

        if ($this->hasContractReceipt()) {
            return 'Vertrag/Dauerbeleg';
        }

        if ($this->hasDocumentReceipt()) {
            return 'Dokumentenbeleg';
        }

        if (!empty($this->receipt_file)) {
            return 'Beleg vorhanden';
        }

        if ($this->hasSystemReceipt()) {
            return 'Clubano-Rechnung vorhanden';
        }

        return 'Beleg fehlt';
    }

    public function receiptEvidenceDetail(): ?string
    {
        if ($this->hasSystemReceipt()) {
            $invoiceNumber = $this->invoice?->invoice_number
                ?: ($this->receipt_meta['invoice_number'] ?? null);

            return $invoiceNumber ? 'Rechnung ' . $invoiceNumber : null;
        }

        if ($this->hasDocumentReceipt()) {
            return $this->receipt_meta['document_title'] ?? $this->receipt_meta['document_name'] ?? null;
        }

        if (! $this->hasContractReceipt()) {
            return null;
        }

        $meta = $this->receipt_meta ?? [];
        $parts = array_filter([
            $meta['contract_reference'] ?? null,
            $meta['contract_location'] ?? null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    public function isJournalReviewed(): bool
    {
        return $this->journal_reviewed_at !== null;
    }

    public function isJournalReceiptChecked(): bool
    {
        return $this->journal_receipt_checked_at !== null;
    }
}
