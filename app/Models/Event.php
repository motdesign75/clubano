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
        'price_per_person',
        'currency',
        'max_participants_per_booking',
        'image_path',
        'tenant_id',
        'category_id',
        'responsible_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'is_public' => 'boolean',
        'booking_enabled' => 'boolean',
        'price_per_person' => 'decimal:2',
        'max_participants_per_booking' => 'integer',
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
        return (float) $this->price_per_person > 0;
    }

    public function getPriceLabelAttribute(): string
    {
        if (! $this->is_paid) {
            return 'Kostenlos';
        }

        return 'Ab ' . number_format((float) $this->price_per_person, 2, ',', '.') . ' ' . strtoupper($this->currency ?: 'EUR');
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
