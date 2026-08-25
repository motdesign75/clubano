<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankImport extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'account_id',
        'uploaded_by',
        'filename',
        'format',
        'status',
        'row_count',
        'imported_count',
        'duplicate_count',
        'booked_count',
        'statement_from',
        'statement_to',
        'meta',
    ];

    protected $casts = [
        'statement_from' => 'date',
        'statement_to' => 'date',
        'meta' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }
}
