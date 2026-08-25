<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorAnnouncement extends Model
{
    public const CATEGORY_PRODUCT_UPDATE = 'product_update';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_CONTRACT = 'contract';
    public const CATEGORY_PRIVACY = 'privacy';

    protected $fillable = [
        'created_by',
        'subject',
        'body_markdown',
        'body_html',
        'cta_label',
        'cta_url',
        'category',
        'recipient_filter',
        'recipient_summary',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'recipient_summary' => 'array',
        'sent_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(OperatorAnnouncementDelivery::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
