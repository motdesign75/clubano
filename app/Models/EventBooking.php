<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'public_form_submission_id',
        'invoice_id',
        'booking_reference',
        'booker_name',
        'booker_email',
        'booker_phone',
        'participant_count',
        'price_per_person',
        'total_amount',
        'currency',
        'payment_status',
        'booking_status',
        'notes',
    ];

    protected $casts = [
        'participant_count' => 'integer',
        'price_per_person' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function submission()
    {
        return $this->belongsTo(PublicFormSubmission::class, 'public_form_submission_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function participants()
    {
        return $this->hasMany(EventBookingParticipant::class)->orderBy('position');
    }
}
