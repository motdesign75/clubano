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
        'position',
        'first_name',
        'last_name',
        'email',
        'phone',
        'answers',
    ];

    protected $casts = [
        'position' => 'integer',
        'answers' => 'array',
    ];

    protected $appends = [
        'full_name',
    ];

    public function booking()
    {
        return $this->belongsTo(EventBooking::class, 'event_booking_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
