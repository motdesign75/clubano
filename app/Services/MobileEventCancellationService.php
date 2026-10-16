<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventBookingParticipant;
use App\Models\EventChangeLog;
use App\Models\EventInvitation;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class MobileEventCancellationService
{
    public function blockedReason(Event $event, EventBooking $booking): ?string
    {
        if (! $event->start || $event->start->lte(now())) {
            return 'Der Termin hat bereits begonnen. Bitte kontaktiere deinen Verein.';
        }

        if ($booking->invoice_id || (float) $booking->gross_amount !== 0.0
            || (float) $booking->total_amount !== 0.0 || (float) $booking->voucher_discount_amount !== 0.0
            || ! in_array($booking->payment_status, ['not_required', 'cancelled'], true)
            || $booking->voucherRedemptions()->exists()
            || $booking->participants()->where(function ($query) {
                $query->where('price_amount', '!=', 0)->orWhere('payment_required', true)
                    ->orWhere('voucher_discount_amount', '>', 0)->orWhereIn('payment_status', ['paid', 'open']);
            })->exists()) {
            return 'Für Änderungen an dieser Buchung kontaktiere bitte deinen Verein. Rechnungen und Zahlungen werden dabei separat geklärt.';
        }

        return null;
    }

    public function cancel(Event $event, Member $member, int $participantId, int $appUserId): void
    {
        DB::transaction(function () use ($event, $member, $participantId, $appUserId) {
            // Match the public booking flow's lock order so released capacity stays consistent.
            $event = Event::where('tenant_id', $member->tenant_id)->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $participant = EventBookingParticipant::whereKey($participantId)
                ->where('member_id', $member->id)
                ->whereHas('booking', fn ($query) => $query->where('event_id', $event->id)->where('tenant_id', $member->tenant_id))
                ->firstOrFail();
            $booking = EventBooking::whereKey($participant->event_booking_id)->lockForUpdate()->firstOrFail();
            $participant = EventBookingParticipant::whereKey($participantId)->where('member_id', $member->id)->lockForUpdate()->firstOrFail();

            if ($participant->payment_status === 'cancelled' || $booking->booking_status === 'cancelled') {
                return;
            }

            $reason = $this->blockedReason($event, $booking);
            abort_if($reason !== null, 422, $reason ?? 'Abmeldung nicht möglich.');

            $before = ['participant_id' => $participant->id, 'payment_status' => $participant->payment_status];
            $participant->update(['payment_status' => 'cancelled']);
            $booking->recalculateTotalsFromParticipants();
            if ($booking->participant_count === 0) {
                $booking->update(['booking_status' => 'cancelled']);
                $booking->submission()->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            EventInvitation::where('event_id', $event->id)->where('tenant_id', $member->tenant_id)
                ->where('member_id', $member->id)->update(['status' => EventInvitation::STATUS_DECLINED, 'responded_at' => now()]);
            EventChangeLog::create([
                'tenant_id' => $member->tenant_id, 'event_id' => $event->id, 'user_id' => null,
                'action' => 'mobile_participant_cancelled',
                'summary' => $member->full_name . ' hat die eigene Teilnahme in Mein Clubano abgemeldet.',
                'before_state' => $before,
                'after_state' => ['participant_id' => $participant->id, 'payment_status' => 'cancelled', 'mobile_app_user_id' => $appUserId],
            ]);
        });
    }
}
