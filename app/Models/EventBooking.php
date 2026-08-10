<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventBooking extends Model
{
    use BelongsToTenant;
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

    public function recalculateTotalsFromParticipants(): void
    {
        $participants = $this->participants()->get();
        $totalAmount = $participants->sum(fn (EventBookingParticipant $participant) => (float) $participant->price_amount);
        $participantCount = max(1, $participants->count());
        $paymentStatuses = $participants->pluck('payment_status');

        $paymentStatus = 'not_required';
        if ($totalAmount > 0) {
            $paymentStatus = $paymentStatuses->contains('open') ? 'open' : 'paid';
        }

        if ($paymentStatuses->every(fn ($status) => $status === 'cancelled')) {
            $paymentStatus = 'cancelled';
        }

        $this->forceFill([
            'participant_count' => $participantCount,
            'price_per_person' => $participantCount > 0 ? round($totalAmount / $participantCount, 2) : 0,
            'total_amount' => $totalAmount,
            'payment_status' => $paymentStatus,
        ])->save();
    }
}
