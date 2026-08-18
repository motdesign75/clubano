<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventBookingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_booking_id',
        'member_id',
        'contact_id',
        'participant_type',
        'position',
        'first_name',
        'last_name',
        'organization_name',
        'email',
        'phone',
        'payment_required',
        'price_amount',
        'voucher_discount_amount',
        'payment_status',
        'payment_reason',
        'source',
        'note',
        'answers',
    ];

    protected $casts = [
        'position' => 'integer',
        'payment_required' => 'boolean',
        'price_amount' => 'decimal:2',
        'voucher_discount_amount' => 'decimal:2',
        'answers' => 'array',
    ];

    protected $appends = [
        'full_name',
        'display_name',
    ];

    public function booking()
    {
        return $this->belongsTo(EventBooking::class, 'event_booking_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->participant_type === 'contact' && $this->contact) {
            return $this->contact->display_name;
        }

        if ($this->organization_name) {
            return $this->organization_name;
        }

        return $this->full_name;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->participant_type) {
            'member' => 'Mitglied',
            'contact' => 'Kontakt',
            default => 'Gast',
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'open' => 'Zahlung offen',
            'paid' => 'Bezahlt',
            'cancelled' => 'Storniert',
            default => 'Keine Zahlung nötig',
        };
    }
}
