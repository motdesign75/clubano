<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataUpdateRequest extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'contact_id',
        'created_by',
        'reviewed_by',
        'recipient_email',
        'recipient_name',
        'token',
        'status',
        'current_data',
        'submitted_data',
        'changes',
        'message',
        'sent_at',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'current_data' => 'array',
        'submitted_data' => 'array',
        'changes' => 'array',
        'sent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function recipientType(): string
    {
        return $this->member_id ? 'member' : 'contact';
    }

    public function recipient(): Member|Contact|null
    {
        return $this->member ?: $this->contact;
    }
}
