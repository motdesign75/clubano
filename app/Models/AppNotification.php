<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    public const TYPE_APP_NEWS = 'app_news';

    protected $fillable = [
        'tenant_id',
        'mobile_app_user_id',
        'member_id',
        'app_news_item_id',
        'type',
        'title',
        'body',
        'data',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function appUser()
    {
        return $this->belongsTo(MobileAppUser::class, 'mobile_app_user_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function appNewsItem()
    {
        return $this->belongsTo(AppNewsItem::class);
    }
}
