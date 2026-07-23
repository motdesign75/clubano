<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Scopes\CurrentTenantScope;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'import_run_id',

        // Block: Mitglied
        'gender',
        'salutation',
        'title',
        'first_name',
        'last_name',
        'organization',
        'birthday',
        'photo',

        // Block: Mitgliedschaft
        'member_id',
        'entry_date',
        'exit_date',
        'termination_date',
        'membership_id',
        'membership_amount',
        'membership_interval',

        // Block: Zahlung
        'payment_method',
        'iban',
        'bic',
        'sepa_mandate_reference',
        'sepa_signed_at',
        'sepa_account_holder',
        'sepa_account_holder_street',
        'sepa_account_holder_zip',
        'sepa_account_holder_city',
        'sepa_account_holder_country',

        // Block: Kommunikation
        'email',
        'mobile',
        'whatsapp_phone',
        'landline',
        'preferred_contact_channel',
        'consent_email',
        'consent_phone',
        'consent_post',
        'consent_whatsapp',
        'consent_data_processing',
        'consent_photo_internal',
        'consent_photo_public',
        'consent_given_at',
        'last_contacted_at',
        'deletion_requested_at',
        'deletion_note',
        'archived_at',

        // Block: Adresse
        'street',
        'address_addition',
        'zip',
        'city',
        'country',
        'care_of',
    ];

    protected $casts = [
        'birthday'         => 'date',
        'entry_date'       => 'date',
        'exit_date'        => 'date',
        'termination_date' => 'date',
        'sepa_signed_at'   => 'date',
        'consent_email'    => 'boolean',
        'consent_phone'    => 'boolean',
        'consent_post'     => 'boolean',
        'consent_whatsapp' => 'boolean',
        'consent_data_processing' => 'boolean',
        'consent_photo_internal' => 'boolean',
        'consent_photo_public' => 'boolean',
        'consent_given_at' => 'datetime',
        'last_contacted_at'=> 'datetime',
        'deletion_requested_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
        'country_name',
        'status',
        'is_archived',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($member) {
            if (Auth::check() && blank($member->tenant_id)) {
                $member->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    // Beziehungen
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function importRun()
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }

    public function customValues()
    {
        return $this->hasMany(CustomMemberValue::class);
    }

    public function protocols()
    {
        return $this->belongsToMany(Protocol::class, 'protocol_member')->withTimestamps();
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->latest('invoice_date');
    }

    public function publicFormSubmissions()
    {
        return $this->hasMany(PublicFormSubmission::class)->latest();
    }

    public function communicationLogs()
    {
        return $this->hasMany(MemberCommunicationLog::class)->latest('sent_at');
    }

    public function credits()
    {
        return $this->hasMany(MemberCredit::class)->latest('credited_at')->latest('id');
    }

    public function availableCredits()
    {
        return $this->credits()->where('remaining_amount', '>', 0);
    }

    // Scopes
    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    // Accessor: Vollständiger Name
    public function getFullNameAttribute()
    {
        $parts = array_filter([$this->title, $this->first_name, $this->last_name]);
        return implode(' ', $parts);
    }

    // Accessor: Ländename (aus Konfigurationsdatei)
    public function getCountryNameAttribute()
    {
        return config('countries.list')[$this->country] ?? $this->country;
    }

    // Accessor: Mitgliedsstatus
    public function getStatusAttribute(): string
    {
        if ($this->archived_at) {
            return 'archiviert';
        }

        $today = now();

        if ($this->exit_date && $this->exit_date->isPast()) {
            return 'ehemalig';
        }

        if ($this->entry_date && $this->entry_date->isFuture()) {
            return 'zukünftig';
        }

        if ($this->entry_date && (!$this->exit_date || $this->exit_date->isFuture())) {
            return 'aktiv';
        }

        return 'zukünftig';
    }

    public function getIsArchivedAttribute(): bool
    {
        return !is_null($this->archived_at);
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'ueberweisung' => 'Überweisung',
            'bar' => 'Bar',
            'sepa_lastschrift' => 'SEPA-Lastschrift',
            default => 'Nicht hinterlegt',
        };
    }

    public function getAvailableCreditBalance(): float
    {
        return (float) $this->availableCredits()->sum('remaining_amount');
    }
}
