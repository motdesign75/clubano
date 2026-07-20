<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Scopes\CurrentTenantScope;

class Contact extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',

        /*
        |--------------------------------------------------------------------------
        | Neue Kontaktfelder
        |--------------------------------------------------------------------------
        */

        // Status / Art
        'contact_type',
        'category',
        'is_active',
        'is_favorite',

        // Organisation
        'organization',
        'department',
        'position',

        // Person
        'gender',
        'salutation',
        'title',
        'first_name',
        'last_name',
        'birthday',
        'photo',

        // Kommunikation
        'email',
        'secondary_email',
        'mobile',
        'phone',
        'fax',
        'website',

        // Adresse
        'street',
        'address_addition',
        'zip',
        'city',
        'state',
        'country',
        'care_of',

        // Beziehung / Herkunft
        'relationship',
        'source',
        'responsible_user_id',

        // Datenschutz / Einwilligung
        'consent_email',
        'consent_phone',
        'consent_post',
        'consent_given_at',

        // Verlauf / Notizen
        'last_contacted_at',
        'notes',
        'internal_notes',

        /*
        |--------------------------------------------------------------------------
        | Alte Felder - bleiben für bestehende Daten/Formulare kompatibel
        |--------------------------------------------------------------------------
        */

        'company',
        'phone_mobile',
        'phone_landline',
        'street_addition',
        'postal_code',
        'status',
        'gdpr_consent',
        'gdpr_consent_at',
        'tags',
    ];

    protected $casts = [
        'birthday'          => 'date',
        'is_active'         => 'boolean',
        'is_favorite'       => 'boolean',
        'consent_email'     => 'boolean',
        'consent_phone'     => 'boolean',
        'consent_post'      => 'boolean',
        'consent_given_at'  => 'datetime',
        'last_contacted_at' => 'datetime',

        // Alte Felder
        'gdpr_consent'      => 'boolean',
        'gdpr_consent_at'   => 'date',
        'tags'              => 'array',
    ];

    protected $appends = [
        'full_name',
        'display_name',
        'primary_email',
        'primary_phone',
        'country_name',
        'status_label',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($contact) {
            if (Auth::check()) {
                $contact->tenant_id = Auth::user()->tenant_id;
            }

            static::normalizeCompatibilityFields($contact);
        });

        static::updating(function ($contact) {
            static::normalizeCompatibilityFields($contact);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopePersons($query)
    {
        return $query->where('contact_type', 'person');
    }

    public function scopeOrganizations($query)
    {
        return $query->where('contact_type', 'organization');
    }

    public function scopeCategory($query, ?string $category)
    {
        if (!$category) {
            return $query;
        }

        return $query->where('category', $category);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($query) use ($search) {
            $query->where('first_name', 'like', '%' . $search . '%')
                ->orWhere('last_name', 'like', '%' . $search . '%')
                ->orWhere('organization', 'like', '%' . $search . '%')
                ->orWhere('company', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('mobile', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%')
                ->orWhere('phone_mobile', 'like', '%' . $search . '%')
                ->orWhere('phone_landline', 'like', '%' . $search . '%')
                ->orWhere('city', 'like', '%' . $search . '%');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute()
    {
        $parts = array_filter([
            $this->title,
            $this->first_name,
            $this->last_name,
        ]);

        return implode(' ', $parts);
    }

    public function getDisplayNameAttribute()
    {
        $organization = $this->organization ?: $this->company;

        if ($organization && $this->full_name) {
            return $organization . ' - ' . $this->full_name;
        }

        if ($organization) {
            return $organization;
        }

        return $this->full_name ?: 'Unbenannter Kontakt';
    }

    public function getPrimaryEmailAttribute()
    {
        return $this->email ?: $this->secondary_email;
    }

    public function getPrimaryPhoneAttribute()
    {
        return $this->mobile
            ?: $this->phone
            ?: $this->phone_mobile
            ?: $this->phone_landline;
    }

    public function getCountryNameAttribute()
    {
        return config('countries.list')[$this->country] ?? $this->country;
    }

    public function getStatusLabelAttribute()
    {
        if (isset($this->is_active)) {
            return $this->is_active ? 'Aktiv' : 'Inaktiv';
        }

        return ucfirst($this->status ?: 'aktiv');
    }

    /*
    |--------------------------------------------------------------------------
    | Hilfsmethoden
    |--------------------------------------------------------------------------
    */

    public function isOrganization(): bool
    {
        return $this->contact_type === 'organization';
    }

    public function isPerson(): bool
    {
        return $this->contact_type === 'person';
    }

    private static function normalizeCompatibilityFields(Contact $contact): void
    {
        if (!$contact->organization && $contact->company) {
            $contact->organization = $contact->company;
        }

        if (!$contact->company && $contact->organization) {
            $contact->company = $contact->organization;
        }

        if (!$contact->mobile && $contact->phone_mobile) {
            $contact->mobile = $contact->phone_mobile;
        }

        if (!$contact->phone_mobile && $contact->mobile) {
            $contact->phone_mobile = $contact->mobile;
        }

        if (!$contact->phone && $contact->phone_landline) {
            $contact->phone = $contact->phone_landline;
        }

        if (!$contact->phone_landline && $contact->phone) {
            $contact->phone_landline = $contact->phone;
        }

        if (!$contact->address_addition && $contact->street_addition) {
            $contact->address_addition = $contact->street_addition;
        }

        if (!$contact->street_addition && $contact->address_addition) {
            $contact->street_addition = $contact->address_addition;
        }

        if (!$contact->zip && $contact->postal_code) {
            $contact->zip = $contact->postal_code;
        }

        if (!$contact->postal_code && $contact->zip) {
            $contact->postal_code = $contact->zip;
        }

        if ($contact->is_active === null) {
            $contact->is_active = ($contact->status ?? 'aktiv') === 'aktiv';
        }

        $contact->status = $contact->is_active ? 'aktiv' : 'inaktiv';

        if ($contact->consent_email || $contact->consent_phone || $contact->consent_post) {
            $contact->gdpr_consent = true;
        }

        if ($contact->gdpr_consent && !$contact->consent_email) {
            $contact->consent_email = true;
        }

        if ($contact->gdpr_consent && !$contact->consent_phone) {
            $contact->consent_phone = true;
        }

        if ($contact->gdpr_consent && !$contact->consent_post) {
            $contact->consent_post = true;
        }

        if (!$contact->consent_given_at && $contact->gdpr_consent_at) {
            $contact->consent_given_at = $contact->gdpr_consent_at;
        }

        if (!$contact->gdpr_consent_at && $contact->consent_given_at) {
            $contact->gdpr_consent_at = $contact->consent_given_at;
        }

        if (!$contact->contact_type) {
            $contact->contact_type = $contact->organization && !$contact->first_name && !$contact->last_name
                ? 'organization'
                : 'person';
        }
    }
}
