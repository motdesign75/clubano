<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Event;
use App\Models\EventBookingParticipant;
use App\Models\EventWaitlistEntry;
use App\Models\Member;
use App\Models\MobileAppUser;
use Illuminate\Support\Facades\DB;

class EventWaitlistService
{
    public function isOpen(Event $event): bool
    {
        return $event->booking_enabled && $event->start?->isFuture()
            && ! $event->registration_deadline_passed && $event->activeBookingForm !== null
            && (int) $event->activeBookingForm->tenant_id === (int) $event->tenant_id;
    }

    public function registered(Event $event, int $memberId): bool
    {
        return EventBookingParticipant::where('member_id', $memberId)->active()
            ->whereHas('booking', fn ($query) => $query->where('event_id', $event->id)
                ->where('tenant_id', $event->tenant_id)
                ->where(fn ($status) => $status->whereNull('booking_status')->orWhere('booking_status', '!=', 'cancelled')))
            ->exists();
    }

    public function update(Event $event, Member $member, bool $join): void
    {
        DB::transaction(function () use ($event, $member, $join) {
            $event = Event::where('tenant_id', $member->tenant_id)->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $entry = EventWaitlistEntry::where('event_id', $event->id)->where('member_id', $member->id)->first();
            if (! $join) {
                $entry?->update(['status' => 'removed']);
                return;
            }
            abort_if($member->archived_at || ! $this->isOpen($event), 422, 'Die Warteliste ist geschlossen.');
            abort_if($this->registered($event, $member->id), 422, 'Du bist bereits angemeldet.');
            if ($entry?->status === 'waiting') {
                return;
            }
            abort_unless($event->is_fully_booked, 422, 'Es sind Plätze frei. Bitte melde dich direkt an.');
            EventWaitlistEntry::updateOrCreate(
                ['event_id' => $event->id, 'member_id' => $member->id],
                ['tenant_id' => $member->tenant_id, 'status' => 'waiting', 'joined_at' => now(), 'notified_at' => null],
            );
        });
    }

    public function notifyAvailable(Event $event): int
    {
        return DB::transaction(function () use ($event) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $entries = EventWaitlistEntry::where('tenant_id', $event->tenant_id)->where('event_id', $event->id)
                ->where('status', 'waiting')->with('member')->orderBy('joined_at')->get();
            $count = 0;
            foreach ($entries as $entry) {
                if (! $entry->member || $entry->member->archived_at || (int) $entry->member->tenant_id !== (int) $event->tenant_id) {
                    $entry->update(['status' => 'removed']);
                    continue;
                }
                if ($this->registered($event, $entry->member_id)) {
                    $entry->update(['status' => 'booked']);
                    continue;
                }
                if (! $this->isOpen($event) || $event->is_fully_booked || $entry->notified_at) {
                    continue;
                }
                $users = MobileAppUser::where('tenant_id', $event->tenant_id)->where('member_id', $entry->member_id)->where('is_active', true)->get();
                foreach ($users as $user) {
                    AppNotification::create([
                        'tenant_id' => $event->tenant_id, 'member_id' => $entry->member_id, 'mobile_app_user_id' => $user->id,
                        'type' => 'event_waitlist', 'title' => 'Plätze frei: ' . $event->title,
                        'body' => 'Für diesen Termin sind wieder Plätze verfügbar. Bitte melde dich verbindlich an. Dein Wartelisteneintrag reserviert keinen Platz.',
                        'data' => ['screen' => 'events', 'event_id' => $event->id], 'sent_at' => now(),
                    ]);
                    $count++;
                }
                if ($users->isNotEmpty()) {
                    $entry->update(['notified_at' => now()]);
                }
            }

            return $count;
        });
    }
}
