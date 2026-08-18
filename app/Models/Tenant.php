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
        'donation_certificates_enabled',
        'donation_certificates_send_enabled',
        'donation_tax_office',
        'donation_tax_number',
        'donation_notice_authority',
        'donation_notice_date',
        'donation_notice_valid_until',
        'donation_purposes',
        'donation_freistellung_document_id',
        'donation_email_body',
        'voucher_template_path',
        'voucher_template_width',
        'voucher_template_height',
        'voucher_code_position',
        'voucher_code_color',
        'voucher_show_qr',
        'voucher_mail_subject',
        'voucher_mail_body',

        // ➕ Stripe / Cashier
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'license_mode',
        'license_expires_at',
        'is_demo',
        'verification_status',
        'verification_notes',
        'verified_at',
        'verified_by_user_id',
        'registration_contact_name',
        'registration_role',
        'registration_website',
        'registration_intent',
        'registration_ip',

        // (Optional / Legacy)
        'stripe_subscription_id',
    ];

    protected $casts = [
        'trial_ends_at'   => 'datetime',
        'license_expires_at' => 'datetime',
        'is_demo' => 'boolean',
        'verified_at' => 'datetime',
        'use_letterhead'  => 'boolean',
        'member_exit_mail_enabled' => 'boolean',
        'donation_certificates_enabled' => 'boolean',
        'donation_certificates_send_enabled' => 'boolean',
        'donation_notice_date' => 'date',
        'donation_notice_valid_until' => 'date',
        'voucher_show_qr' => 'boolean',
        'voucher_template_width' => 'integer',
        'voucher_template_height' => 'integer',
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

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function donationFreistellungDocument()
    {
        return $this->belongsTo(Document::class, 'donation_freistellung_document_id');
    }

    public function canIssueDonationCertificates(): bool
    {
        return $this->donationCertificateReadiness()['can_issue'];
    }

    public function donationCertificateReadiness(): array
    {
        $missing = [];
        $document = $this->donationFreistellungDocument;

        if (! $this->donation_certificates_enabled) {
            $missing[] = 'Spendenbescheinigungen sind nicht aktiviert.';
        }

        if (! $document || $document->archived_at || $document->status !== Document::STATUS_ACTIVE) {
            $missing[] = 'Gültiger Freistellungsbescheid fehlt.';
        }

        foreach ([
            'donation_tax_office' => 'Finanzamt',
            'donation_tax_number' => 'Steuernummer',
            'donation_notice_authority' => 'Bescheid von',
            'donation_notice_date' => 'Bescheiddatum',
            'donation_purposes' => 'begünstigte Zwecke',
        ] as $field => $label) {
            if (blank($this->{$field})) {
                $missing[] = $label . ' fehlt.';
            }
        }

        if ($this->donation_notice_date && $this->donation_notice_date->lt(now()->subYears(5))) {
            $missing[] = 'Der Freistellungsbescheid ist älter als fünf Jahre.';
        }

        if ($this->donation_notice_valid_until && $this->donation_notice_valid_until->isPast()) {
            $missing[] = 'Der hinterlegte Bescheid ist abgelaufen.';
        }

        if ($document?->expires_at && $document->expires_at->isPast()) {
            $missing[] = 'Das hinterlegte Dokument ist abgelaufen.';
        }

        if (! empty($missing)) {
            return [
                'status' => $this->donation_certificates_enabled ? 'incomplete' : 'not_configured',
                'label' => $this->donation_certificates_enabled ? 'Unvollständig' : 'Nicht eingerichtet',
                'message' => 'Zuwendungsbestätigungen sind noch gesperrt.',
                'missing' => $missing,
                'can_issue' => false,
            ];
        }

        if ($this->donation_notice_date?->lte(now()->subYears(4)->addMonths(3)) || $this->donation_notice_valid_until?->lte(now()->addDays(90))) {
            return [
                'status' => 'expiring',
                'label' => 'Läuft bald ab',
                'message' => 'Zuwendungsbestätigungen sind möglich, aber der Nachweis sollte bald erneuert werden.',
                'missing' => [],
                'can_issue' => true,
            ];
        }

        return [
            'status' => 'active',
            'label' => 'Aktiv',
            'message' => 'Zuwendungsbestätigungen können erstellt werden.',
            'missing' => [],
            'can_issue' => true,
        ];
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

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    public function getVerificationStatusLabelAttribute(): string
    {
        return match ($this->verification_status) {
            'verified' => 'Geprüft',
            'suspicious' => 'Verdächtig',
            'rejected' => 'Abgelehnt',
            default => 'Prüfung offen',
        };
    }

    public function getVerificationStatusToneAttribute(): string
    {
        return match ($this->verification_status) {
            'verified' => 'ok',
            'suspicious', 'rejected' => 'risk',
            default => 'watch',
        };
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
