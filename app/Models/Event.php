<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Scopes\CurrentTenantScope;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'location',
        'start',
        'end',
        'is_public',
        'booking_enabled',
        'attendance_enabled',
        'response_required',
        'counts_toward_required_hours',
        'reminders_enabled',
        'price_per_person',
        'member_price_per_person',
        'organization_bookings_free',
        'currency',
        'max_participants_per_booking',
        'image_path',
        'tenant_id',
        'category_id',
        'responsible_user_id',
        'target_tag_id',
        'created_by',
        'updated_by',
        'recurrence_group_id',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_until',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'is_public' => 'boolean',
        'booking_enabled' => 'boolean',
        'attendance_enabled' => 'boolean',
        'response_required' => 'boolean',
        'counts_toward_required_hours' => 'boolean',
        'reminders_enabled' => 'boolean',
        'price_per_person' => 'decimal:2',
        'member_price_per_person' => 'decimal:2',
        'organization_bookings_free' => 'boolean',
        'max_participants_per_booking' => 'integer',
        'recurrence_interval' => 'integer',
        'recurrence_until' => 'date',
    ];

    /**
     * Global Scope & automatisches Setzen von tenant_id
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($event) {
            if (Auth::check()) {
                $event->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Beziehung: Event gehört zu einem Verein
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function targetTag()
    {
        return $this->belongsTo(Tag::class, 'target_tag_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function changeLogs()
    {
        return $this->hasMany(EventChangeLog::class)->latest();
    }

    public function publicForms()
    {
        return $this->hasMany(PublicForm::class);
    }

    public function activeBookingForm()
    {
        return $this->hasOne(PublicForm::class)
            ->where('form_type', 'event')
            ->where('is_active', true)
            ->latestOfMany();
    }

    public function bookingSubmissions()
    {
        return $this->hasMany(PublicFormSubmission::class)->latest();
    }

    public function bookings()
    {
        return $this->hasMany(EventBooking::class)->latest();
    }

    public function shifts()
    {
        return $this->hasMany(EventShift::class)->orderBy('starts_at')->orderBy('sort_order');
    }

    public function shiftAssignments()
    {
        return $this->hasMany(EventShiftAssignment::class)->latest();
    }

    public function attendances()
    {
        return $this->hasMany(EventAttendance::class);
    }

    public function invitations()
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return Route::has('events.image')
            ? route('events.image', $this->id)
            : asset('storage/' . ltrim($this->image_path, '/'));
    }

    public function getIsPaidAttribute(): bool
    {
        return max((float) $this->price_per_person, (float) $this->member_price_per_person) > 0;
    }

    public function getPriceLabelAttribute(): string
    {
        if (! $this->is_paid) {
            return 'Kostenlos';
        }

        $prices = collect([
            (float) $this->member_price_per_person,
            $this->organization_bookings_free ? 0.0 : null,
            (float) $this->price_per_person,
        ])->filter(fn ($price) => $price !== null);

        if ($prices->isEmpty()) {
            return 'Kostenlos';
        }

        if ((float) $prices->min() <= 0 && (float) $prices->max() > 0) {
            return 'Teilweise kostenlos';
        }

        return 'Ab ' . number_format($prices->min(), 2, ',', '.') . ' ' . strtoupper($this->currency ?: 'EUR');
    }

    public function priceForParticipantType(string $participantType): float
    {
        return round((float) ($participantType === 'member' ? $this->member_price_per_person : $this->price_per_person), 2);
    }

    public function priceForPublicBookingParticipant(array $participant, string $bookingMode): float
    {
        if ($bookingMode === 'organization' && $this->organization_bookings_free) {
            return 0.0;
        }

        return $this->priceForParticipantType($participant['participant_type'] ?? 'guest');
    }

    public function getMonthGroupLabelAttribute(): string
    {
        return $this->start?->translatedFormat('F Y');
    }

    public function getDateAccentColorAttribute(): string
    {
        return $this->category?->color ?: '#2563EB';
    }

    public function getShortLocationAttribute(): string
    {
        if (! $this->location) {
            return 'Ort folgt';
        }

        return Str::limit($this->location, 80);
    }

    public function getResponsibleNameAttribute(): ?string
    {
        return $this->responsibleUser?->name;
    }

    /**
     * Optionaler lokaler Scope für aktuellen Verein
     */
    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }
}
