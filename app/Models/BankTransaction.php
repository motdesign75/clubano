<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'tenant_id',
        'bank_import_id',
        'account_id',
        'transaction_id',
        'selected_account_id',
        'booking_date',
        'value_date',
        'amount',
        'currency',
        'direction',
        'counterparty_name',
        'counterparty_iban',
        'purpose',
        'end_to_end_id',
        'bank_reference',
        'fingerprint',
        'status',
        'raw_data',
        'receipt_file',
        'receipt_kind',
        'receipt_meta',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'raw_data' => 'array',
        'receipt_meta' => 'array',
    ];

    public function bankImport()
    {
        return $this->belongsTo(BankImport::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function selectedAccount()
    {
        return $this->belongsTo(Account::class, 'selected_account_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_READY => 'bereit',
            self::STATUS_BOOKED => 'gebucht',
            self::STATUS_DUPLICATE => 'Dublette',
            self::STATUS_IGNORED => 'ignoriert',
            default => 'zu prüfen',
        };
    }
}
