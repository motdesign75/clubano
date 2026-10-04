<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MobileAppUser;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MobileAppSyncService
{
    public function sync(Tenant $tenant, bool $sendInvitations = true): array
    {
        $eligibleMembers = $this->eligibleMembers($tenant);
        $eligibleMemberIds = $eligibleMembers->pluck('id');

        $stats = [
            'synced' => 0,
            'invited' => 0,
            'activated' => MobileAppUser::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('accepted_at')
                ->count(),
            'failed' => 0,
            'removed' => 0,
            'ignored' => 0,
        ];

        MobileAppUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('member_id', $eligibleMemberIds)
            ->whereNull('sync_ignored_at')
            ->where('sync_status', '!=', MobileAppUser::STATUS_MANUAL)
            ->each(function (MobileAppUser $appUser) use (&$stats) {
                $appUser->tokens()->delete();
                $appUser->forceFill([
                    'is_active' => false,
                    'sync_status' => MobileAppUser::STATUS_REMOVED,
                    'sync_error' => null,
                ])->save();

                $stats['removed']++;
            });

        foreach ($eligibleMembers as $member) {
            $result = $this->syncMember($tenant, $member, $sendInvitations);
            $stats[$result]++;
        }

        $stats['ignored'] = MobileAppUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('sync_ignored_at')
            ->count();

        $stats['failed'] = MobileAppUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('sync_status', MobileAppUser::STATUS_FAILED)
            ->count();

        return $stats;
    }

    public function eligibleMembers(Tenant $tenant): Collection
    {
        return Member::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('archived_at')
            ->whereNotNull('email')
            ->when($tenant->mobile_app_sync_tag_id, function ($query, int $tagId) {
                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereKey($tagId));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->filter(fn (Member $member) => filled(trim((string) $member->email)))
            ->values();
    }

    private function syncMember(Tenant $tenant, Member $member, bool $sendInvitation): string
    {
        $email = mb_strtolower(trim((string) $member->email));

        if ($email === '') {
            return 'failed';
        }

        $duplicate = MobileAppUser::query()
            ->whereRaw('LOWER(username) = ?', [$email])
            ->where('member_id', '!=', $member->id)
            ->first();

        if ($duplicate) {
            MobileAppUser::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'member_id' => $member->id,
                ],
                [
                    'username' => 'mitglied-' . $member->id . '@clubano.local',
                    'password' => Str::random(48),
                    'is_active' => false,
                    'sync_status' => MobileAppUser::STATUS_FAILED,
                    'sync_error' => 'E-Mail-Adresse wird bereits für einen anderen App-Zugang genutzt.',
                    'synced_at' => now(),
                ],
            );

            return 'failed';
        }

        $appUser = MobileAppUser::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
        ]);

        $isNew = ! $appUser->exists;
        $accepted = filled($appUser->accepted_at);

        $appUser->fill([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'username' => $email,
            'is_active' => true,
            'sync_status' => $accepted ? MobileAppUser::STATUS_ACTIVE : MobileAppUser::STATUS_INVITED,
            'sync_error' => null,
            'synced_at' => now(),
        ]);

        if ($isNew || blank($appUser->password)) {
            $appUser->password = Str::random(48);
        }

        if (! $accepted && blank($appUser->invitation_token)) {
            $appUser->invitation_token = Str::random(64);
        }

        $appUser->save();

        if (! $accepted && $sendInvitation) {
            $this->sendInvitation($tenant, $member, $appUser);
            $appUser->forceFill(['invitation_sent_at' => now()])->save();

            return 'invited';
        }

        return 'synced';
    }

    private function sendInvitation(Tenant $tenant, Member $member, MobileAppUser $appUser): void
    {
        $url = route('mobile-app-invitations.show', $appUser->invitation_token);
        $name = trim($member->full_name) ?: 'Mitglied';

        Mail::raw(
            "Hallo {$name},\n\n".
            "{$tenant->name} nutzt die App Mein Clubano. Über den folgenden Link kannst du deinen App-Zugang aktivieren:\n\n".
            "{$url}\n\n".
            "Dieser Zugang gilt nur für die Mitglieder-App und nicht für die Clubano-Verwaltung.\n\n".
            "Viele Grüße\n{$tenant->name}",
            function ($message) use ($member, $tenant) {
                $message->to($member->email)
                    ->subject('Dein Zugang zur Mein Clubano App');

                if (filled($tenant->email)) {
                    $message->replyTo($tenant->email, $tenant->name);
                }
            },
        );
    }

    public function activate(MobileAppUser $appUser, string $password): void
    {
        $appUser->tokens()->delete();
        $appUser->forceFill([
            'password' => Hash::make($password),
            'is_active' => true,
            'sync_status' => MobileAppUser::STATUS_ACTIVE,
            'accepted_at' => now(),
            'invitation_token' => null,
            'sync_error' => null,
        ])->save();
    }
}
