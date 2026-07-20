<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory;
    // use SoftDeletes;

    protected $fillable = [
        'tenant_id',

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

        // Block: Kommunikation
        'email',
        'mobile',
        'landline',

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
    ];

    protected $appends = [
        'full_name',
        'country_name',
    ];

    /**
     * Beziehung: Mitglied gehört zu einem Verein (Mandant)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Beziehung: Mitglied hat eine Mitgliedschaft
     */
    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * Beziehung: Benutzerdefinierte Felder (Werte)
     */
    public function customValues()
    {
        return $this->hasMany(CustomMemberValue::class);
    }

    /**
     * Scope: Filter für aktuellen Mandanten
     */
    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    /**
     * Accessor: Vollständiger Name (ggf. mit Titel)
     */
    public function getFullNameAttribute()
    {
        $parts = array_filter([$this->title, $this->first_name, $this->last_name]);
        return implode(' ', $parts);
    }

    /**
     * Accessor: Land als ausgeschriebener Name
     */
    public function getCountryNameAttribute()
    {
        return config('countries.list')[$this->country] ?? $this->country;
    }
}
