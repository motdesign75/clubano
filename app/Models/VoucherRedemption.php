<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'voucher_id',
        'event_booking_id',
        'event_booking_participant_id',
        'amount',
        'currency',
        'redeemed_by_name',
        'redeemed_by_email',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function booking()
    {
        return $this->belongsTo(EventBooking::class, 'event_booking_id');
    }

    public function participant()
    {
        return $this->belongsTo(EventBookingParticipant::class, 'event_booking_participant_id');
    }
}
