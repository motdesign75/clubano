<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorAnnouncement extends Model
{
    protected $fillable = [
        'created_by',
        'subject',
        'body_markdown',
        'body_html',
        'cta_label',
        'cta_url',
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
