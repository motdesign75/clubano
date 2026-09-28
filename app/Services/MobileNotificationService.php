<?php

namespace App\Services;

use App\Models\AppNewsItem;
use App\Models\AppNotification;
use App\Models\MobileAppUser;
use App\Models\MobilePushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobileNotificationService
{
    public function __construct(private readonly ApnsPushService $apns)
    {
    }

    public function notifyNews(AppNewsItem $news): int
    {
        if (! $news->push_enabled || $news->status !== AppNewsItem::STATUS_PUBLISHED) {
            return 0;
        }

        $created = 0;
        $users = MobileAppUser::query()
            ->where('tenant_id', $news->tenant_id)
            ->where('is_active', true)
            ->get();

        foreach ($users as $user) {
            $notification = AppNotification::firstOrCreate(
                [
                    'mobile_app_user_id' => $user->id,
                    'app_news_item_id' => $news->id,
                ],
                [
                    'tenant_id' => $news->tenant_id,
                    'member_id' => $user->member_id,
                    'type' => AppNotification::TYPE_APP_NEWS,
                    'title' => $news->title,
                    'body' => $news->teaser ?: $news->body,
                    'data' => [
                        'screen' => 'news',
                        'app_news_item_id' => $news->id,
                    ],
                    'sent_at' => now(),
                ],
            );

            if ($notification->wasRecentlyCreated) {
                $created++;
            }
        }

        $sent = $this->sendExpoPushes($news) + $this->sendApnsPushes($news);

        if ($sent > 0 && blank($news->push_sent_at)) {
            $news->forceFill(['push_sent_at' => now()])->save();
        }

        return $created;
    }

    private function sendExpoPushes(AppNewsItem $news): int
    {
        $tokens = MobilePushToken::query()
            ->where('tenant_id', $news->tenant_id)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->where('provider', 'expo')
                    ->orWhere('token', 'like', 'ExponentPushToken[%');
            })
            ->pluck('token')
            ->filter(fn (string $token) => str_starts_with($token, 'ExponentPushToken['))
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $messages = $tokens->map(fn (string $token) => [
            'to' => $token,
            'sound' => 'default',
            'title' => $news->title,
            'body' => $news->teaser ?: 'Neue Mitteilung in Mein Clubano',
            'data' => [
                'screen' => 'news',
                'app_news_item_id' => $news->id,
            ],
        ])->all();

        try {
            Http::timeout(10)
                ->acceptJson()
                ->post('https://exp.host/--/api/v2/push/send', $messages)
                ->throw();

            return $tokens->count();
        } catch (\Throwable $exception) {
            Log::warning('Expo push delivery failed', [
                'app_news_item_id' => $news->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return 0;
    }

    private function sendApnsPushes(AppNewsItem $news): int
    {
        $tokens = MobilePushToken::query()
            ->where('tenant_id', $news->tenant_id)
            ->where('provider', 'apns')
            ->whereNull('revoked_at')
            ->pluck('token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($tokens as $token) {
            if ($this->apns->send(
                $token,
                $news->title,
                $news->teaser ?: 'Neue Mitteilung in Mein Clubano',
                [
                    'screen' => 'news',
                    'app_news_item_id' => $news->id,
                ],
            )) {
                $sent++;
            }
        }

        return $sent;
    }
}
