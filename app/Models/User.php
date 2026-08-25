<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'SAdmin';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_STAFF = 'Bearbeiten';
    public const ROLE_TREASURER = 'Kasse';
    public const ROLE_SECRETARY = 'Schriftführung';
    public const ROLE_EVENT_MANAGER = 'Veranstaltungen';
    public const ROLE_VIEWER = 'Lesen';

    /**
     * Felder, die massenweise zuweisbar sind.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'update_notice_dismissed_version',
        'operator_updates_unsubscribed_at',
    ];

    /**
     * Felder, die bei Serialisierung verborgen werden.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Automatisch anzuhängende Accessors.
     *
     * @var array<int, string>
     */
    protected $appends = [
        // Beispiel: 'profile_photo_url',
    ];

    /**
     * Typ-Casts für Felder.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'operator_updates_unsubscribed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Beziehung zum zugehörigen Mandanten (Tenant/Verein).
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function hasVerifiedEmail(): bool
    {
        if ($this->email_verified_at !== null) {
            return true;
        }

        if (! $this->created_at) {
            return false;
        }

        $rolloutAt = config('clubano.registration.email_verification_required_since');

        if (! $rolloutAt) {
            return false;
        }

        try {
            return $this->created_at->lt(Carbon::parse($rolloutAt));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    public static function manageableRolesFor(self $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return [
                self::ROLE_SUPERADMIN,
                self::ROLE_ADMIN,
                self::ROLE_STAFF,
                self::ROLE_TREASURER,
                self::ROLE_SECRETARY,
                self::ROLE_EVENT_MANAGER,
                self::ROLE_VIEWER,
            ];
        }

        if ($actor->isAdmin()) {
            return [
                self::ROLE_ADMIN,
                self::ROLE_STAFF,
                self::ROLE_TREASURER,
                self::ROLE_SECRETARY,
                self::ROLE_EVENT_MANAGER,
                self::ROLE_VIEWER,
            ];
        }

        return [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function roleOptionsFor(self $actor): array
    {
        $allowed = self::manageableRolesFor($actor);

        return collect($allowed)
            ->map(fn (string $role) => [
                'value' => $role,
                'label' => self::roleLabelFor($role),
                'description' => self::roleDescriptionFor($role),
            ])
            ->values()
            ->all();
    }

    public static function normalizeRole(?string $role): string
    {
        return match ($role) {
            self::ROLE_SUPERADMIN => self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN => self::ROLE_ADMIN,
            self::ROLE_STAFF, 'Mitarbeiter', 'User' => self::ROLE_STAFF,
            self::ROLE_TREASURER, 'Kassierer', 'Kassenwart', 'Schatzmeister', 'Schatzmeisterin' => self::ROLE_TREASURER,
            self::ROLE_SECRETARY, 'Schriftführer', 'Schriftführerin', 'Schriftwart' => self::ROLE_SECRETARY,
            self::ROLE_EVENT_MANAGER, 'Eventmanager', 'Veranstaltungsleitung' => self::ROLE_EVENT_MANAGER,
            self::ROLE_VIEWER => self::ROLE_VIEWER,
            default => self::ROLE_VIEWER,
        };
    }

    public static function roleLabelFor(?string $role): string
    {
        return match (self::normalizeRole($role)) {
            self::ROLE_SUPERADMIN => 'Superadmin',
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_STAFF => 'Bearbeiten',
            self::ROLE_TREASURER => 'Kasse',
            self::ROLE_SECRETARY => 'Schriftführung',
            self::ROLE_EVENT_MANAGER => 'Veranstaltungen',
            self::ROLE_VIEWER => 'Lesen',
            default => 'Unbekannt',
        };
    }

    public static function roleDescriptionFor(?string $role): string
    {
        return match (self::normalizeRole($role)) {
            self::ROLE_SUPERADMIN => 'Plattformweite Vollrechte für Systembetrieb und Sonderfälle.',
            self::ROLE_ADMIN => 'Verwaltet Verein, Benutzer, Finanzen und alle sensiblen Einstellungen.',
            self::ROLE_STAFF => 'Arbeitet operativ mit Mitgliedern, Veranstaltungen, Formularen, Vorlagen und Protokollen.',
            self::ROLE_TREASURER => 'Verwaltet Finanzen, Rechnungen, Beiträge, SEPA, Spenden und Auswertungen.',
            self::ROLE_SECRETARY => 'Erstellt und verwaltet Protokolle, Dokumente und schriftliche Vereinsarbeit.',
            self::ROLE_EVENT_MANAGER => 'Plant Veranstaltungen, Dienstpläne, Teilnehmerlisten und Anwesenheiten.',
            self::ROLE_VIEWER => 'Darf Inhalte ansehen, aber nichts ändern.',
            default => 'Keine Beschreibung hinterlegt.',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function permissionProfiles(): array
    {
        return [
            [
                'role' => self::ROLE_ADMIN,
                'label' => self::roleLabelFor(self::ROLE_ADMIN),
                'description' => self::roleDescriptionFor(self::ROLE_ADMIN),
                'access' => [
                    ['area' => 'Mitglieder und Kontakte', 'level' => 'voll'],
                    ['area' => 'Veranstaltungen und Formulare', 'level' => 'voll'],
                    ['area' => 'Finanzen, Rechnungen und SEPA', 'level' => 'voll'],
                    ['area' => 'Benutzer, Verein und Einstellungen', 'level' => 'voll'],
                ],
            ],
            [
                'role' => self::ROLE_STAFF,
                'label' => self::roleLabelFor(self::ROLE_STAFF),
                'description' => self::roleDescriptionFor(self::ROLE_STAFF),
                'access' => [
                    ['area' => 'Mitglieder und Kontakte', 'level' => 'bearbeiten'],
                    ['area' => 'Veranstaltungen, Formulare, Vorlagen, Protokolle', 'level' => 'bearbeiten'],
                    ['area' => 'Finanzen, Rechnungen, SEPA, Benutzer, Verein', 'level' => 'kein_zugriff'],
                ],
            ],
            [
                'role' => self::ROLE_TREASURER,
                'label' => self::roleLabelFor(self::ROLE_TREASURER),
                'description' => self::roleDescriptionFor(self::ROLE_TREASURER),
                'access' => [
                    ['area' => 'Finanzen, Rechnungen, SEPA, Spenden, Haushaltsplan', 'level' => 'voll'],
                    ['area' => 'Mitglieder und Kontakte', 'level' => 'lesen'],
                    ['area' => 'Veranstaltungen, Protokolle, Dokumente', 'level' => 'lesen'],
                    ['area' => 'Benutzer, Verein und Einstellungen', 'level' => 'kein_zugriff'],
                ],
            ],
            [
                'role' => self::ROLE_SECRETARY,
                'label' => self::roleLabelFor(self::ROLE_SECRETARY),
                'description' => self::roleDescriptionFor(self::ROLE_SECRETARY),
                'access' => [
                    ['area' => 'Protokolle und Dokumente', 'level' => 'bearbeiten'],
                    ['area' => 'Mitglieder, Kontakte und Veranstaltungen', 'level' => 'lesen'],
                    ['area' => 'Finanzen, Rechnungen, SEPA', 'level' => 'kein_zugriff'],
                    ['area' => 'Benutzer, Verein und Einstellungen', 'level' => 'kein_zugriff'],
                ],
            ],
            [
                'role' => self::ROLE_EVENT_MANAGER,
                'label' => self::roleLabelFor(self::ROLE_EVENT_MANAGER),
                'description' => self::roleDescriptionFor(self::ROLE_EVENT_MANAGER),
                'access' => [
                    ['area' => 'Veranstaltungen, Dienstpläne, Teilnehmer und Anwesenheit', 'level' => 'bearbeiten'],
                    ['area' => 'Mitglieder und Kontakte', 'level' => 'lesen'],
                    ['area' => 'Finanzen, Rechnungen, SEPA', 'level' => 'kein_zugriff'],
                    ['area' => 'Benutzer, Verein und Einstellungen', 'level' => 'kein_zugriff'],
                ],
            ],
            [
                'role' => self::ROLE_VIEWER,
                'label' => self::roleLabelFor(self::ROLE_VIEWER),
                'description' => self::roleDescriptionFor(self::ROLE_VIEWER),
                'access' => [
                    ['area' => 'Inhalte im Verein', 'level' => 'lesen'],
                    ['area' => 'Änderungen, Versand, Finanzen, Einstellungen', 'level' => 'kein_zugriff'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    protected static function roleHierarchy(): array
    {
        return [
            self::ROLE_VIEWER => 10,
            self::ROLE_STAFF => 20,
            self::ROLE_TREASURER => 20,
            self::ROLE_SECRETARY => 20,
            self::ROLE_EVENT_MANAGER => 20,
            self::ROLE_ADMIN => 30,
            self::ROLE_SUPERADMIN => 40,
        ];
    }

    public function isSuperAdmin(): bool
    {
        return self::normalizeRole($this->role) === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->hasRoleAtLeast(self::ROLE_ADMIN);
    }

    public function isStaff(): bool
    {
        return in_array(self::normalizeRole($this->role), [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
        ], true);
    }

    public function canManageMembers(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
        ]);
    }

    public function canManageForms(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
            self::ROLE_EVENT_MANAGER,
        ]);
    }

    public function canManageProjects(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
        ]);
    }

    public function canManageContacts(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
        ]);
    }

    public function canManageProtocols(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
            self::ROLE_SECRETARY,
        ]);
    }

    public function canManageDocuments(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
            self::ROLE_SECRETARY,
        ]);
    }

    public function canManageFinance(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_TREASURER,
        ]);
    }

    public function canManageTenantSettings(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
        ]);
    }

    public function canManageEvents(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
            self::ROLE_EVENT_MANAGER,
        ]);
    }

    public function hasPermission(string $permission): bool
    {
        return match ($permission) {
            'members' => $this->canManageMembers(),
            'contacts' => $this->canManageContacts(),
            'forms' => $this->canManageForms(),
            'projects' => $this->canManageProjects(),
            'protocols' => $this->canManageProtocols(),
            'documents' => $this->canManageDocuments(),
            'events' => $this->canManageEvents(),
            'finance' => $this->canManageFinance(),
            'settings' => $this->canManageTenantSettings(),
            default => self::isKnownRole($permission) && $this->hasRoleAtLeast($permission),
        };
    }

    public static function isKnownRole(?string $role): bool
    {
        return in_array($role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
            'Mitarbeiter',
            'User',
            self::ROLE_TREASURER,
            'Kassierer',
            'Kassenwart',
            'Schatzmeister',
            'Schatzmeisterin',
            self::ROLE_SECRETARY,
            'Schriftführer',
            'Schriftführerin',
            'Schriftwart',
            self::ROLE_EVENT_MANAGER,
            'Eventmanager',
            'Veranstaltungsleitung',
            self::ROLE_VIEWER,
        ], true);
    }

    public function roleLabel(): string
    {
        return self::roleLabelFor($this->role);
    }

    public function hasRoleAtLeast(string $requiredRole): bool
    {
        $currentRole = self::normalizeRole($this->role);
        $requiredRole = self::normalizeRole($requiredRole);
        $hierarchy = self::roleHierarchy();

        return ($hierarchy[$currentRole] ?? 0) >= ($hierarchy[$requiredRole] ?? PHP_INT_MAX);
    }

    /**
     * @param array<int, string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array(self::normalizeRole($this->role), $roles, true);
    }
}
