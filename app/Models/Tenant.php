<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class Tenant extends Model
{
    use HasFactory;
    use Billable;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'logo',
        'logo_path',
        'address',
        'zip',
        'city',
        'phone',
        'register_number',
        'creditor_identifier',
        'iban',
        'bic',
        'bank_name',
        'chairman',
        'letterhead',
        'pdf_template',
        'chairman_name',
        'use_letterhead',

        // ➕ SMTP Felder
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'member_exit_mail_enabled',
        'member_exit_mail_subject',
        'member_exit_mail_body',

        // ➕ Stripe / Cashier
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'license_mode',
        'license_expires_at',

        // (Optional / Legacy)
        'stripe_subscription_id',
    ];

    protected $casts = [
        'trial_ends_at'   => 'datetime',
        'license_expires_at' => 'datetime',
        'use_letterhead'  => 'boolean',
        'member_exit_mail_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (!$tenant->invite_code) {
                $tenant->invite_code = Str::uuid();
            }
        });

        // ✅ NEU: Mail bei neuer Registrierung
        static::created(function (Tenant $tenant) {

            try {
                Mail::raw(
                    "🚀 Neuer Verein in Clubano registriert\n\n".
                    "Name: {$tenant->name}\n".
                    "E-Mail: ".($tenant->email ?? 'nicht angegeben')."\n".
                    "Zeit: ".now()->format('d.m.Y H:i'),
                    function ($message) {
                        $message->to('system@clubano.de')
                                ->subject('🚀 Neuer Clubano Verein');
                    }
                );

            } catch (\Throwable $e) {
                Log::error('Admin-Mail fehlgeschlagen: '.$e->getMessage());
            }

        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function invitationCode()
    {
        return $this->hasOne(InvitationCode::class);
    }

    /**
     * ✅ Cashier: explizit Tenant -> Subscriptions über tenant_id erzwingen.
     */
    public function subscriptions()
    {
        return $this->hasMany(\Laravel\Cashier\Subscription::class, 'tenant_id');
    }

    public function getLogoStoragePathAttribute(): ?string
    {
        return $this->logo_path ?: $this->logo;
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = $this->logo_storage_path;

        return $path ? Storage::url($path) : null;
    }

    public function hasComplimentaryAccess(): bool
    {
        if (! in_array($this->license_mode, ['beta', 'gifted'], true)) {
            return false;
        }

        return $this->license_expires_at === null || $this->license_expires_at->isFuture();
    }

    public function getLicenseModeLabelAttribute(): string
    {
        return match ($this->license_mode) {
            'beta' => 'Pilotlizenz',
            'gifted' => 'Freilizenz',
            default => 'Standard',
        };
    }

    public function canStartSelfServeTrial(): bool
    {
        return ! $this->subscribed('default')
            && ! $this->hasComplimentaryAccess()
            && $this->trial_ends_at === null;
    }

    public function startSelfServeTrialIfEligible(?int $days = null): bool
    {
        if (! $this->canStartSelfServeTrial()) {
            return false;
        }

        $trialDays = max(1, (int) ($days ?? config('clubano.trial_days', 14)));

        $this->forceFill([
            'trial_ends_at' => Carbon::now()->addDays($trialDays),
        ])->save();

        return true;
    }
}
