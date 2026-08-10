<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalTenants = Tenant::count();
        $totalUsers = User::count();
        $tenantIds = Tenant::query()->pluck('id');

        $platformStats = [
            'tenants' => $totalTenants,
            'users' => $totalUsers,
            'members' => $this->countTable('members'),
            'events' => $this->countTable('events'),
            'documents' => $this->countTable('documents'),
            'new_tenants_30_days' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
            'licensed' => Tenant::whereIn('license_mode', ['beta', 'gifted'])->count(),
            'expired_trials' => Tenant::whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now())->count(),
            'verification_pending' => Tenant::where('verification_status', 'pending')->count(),
            'verification_risk' => Tenant::whereIn('verification_status', ['suspicious', 'rejected'])->count(),
        ];

        $tenantMetrics = [
            'members' => $this->countByTenant('members'),
            'active_members' => $this->countByTenant('members', fn ($query) => $query->whereNull('archived_at')),
            'users' => $this->countByTenant('users'),
            'events' => $this->countByTenant('events'),
            'protocols' => $this->countByTenant('protocols'),
            'tasks' => $this->countByTenant('tasks'),
            'documents' => $this->countByTenant('documents'),
            'forms' => $this->countByTenant('public_forms'),
            'invitations' => $this->countByTenant('event_invitations'),
            'donations' => $this->countByTenant('donations'),
            'accounts' => $this->countByTenant('accounts'),
            'imports' => $this->countByTenant('import_runs'),
            'failed_imports' => $this->countByTenant('import_runs', fn ($query) => $query->where('status', '!=', 'completed')),
            'unverified_users' => $this->countByTenant('users', fn ($query) => $query->whereNull('email_verified_at')),
            'admin_users' => $this->countByTenant('users', fn ($query) => $query->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])),
        ];

        $lastActivity = $this->lastActivityByTenant($tenantIds->all());

        $allTenants = Tenant::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Tenant $tenant) use ($tenantMetrics, $lastActivity) {
                $tenantId = (int) $tenant->id;
                $tenant->admin_metrics = [
                    'members' => $tenantMetrics['members'][$tenantId] ?? 0,
                    'active_members' => $tenantMetrics['active_members'][$tenantId] ?? 0,
                    'users' => $tenantMetrics['users'][$tenantId] ?? 0,
                    'events' => $tenantMetrics['events'][$tenantId] ?? 0,
                    'protocols' => $tenantMetrics['protocols'][$tenantId] ?? 0,
                    'tasks' => $tenantMetrics['tasks'][$tenantId] ?? 0,
                    'documents' => $tenantMetrics['documents'][$tenantId] ?? 0,
                    'forms' => $tenantMetrics['forms'][$tenantId] ?? 0,
                    'invitations' => $tenantMetrics['invitations'][$tenantId] ?? 0,
                    'donations' => $tenantMetrics['donations'][$tenantId] ?? 0,
                    'accounts' => $tenantMetrics['accounts'][$tenantId] ?? 0,
                    'imports' => $tenantMetrics['imports'][$tenantId] ?? 0,
                    'failed_imports' => $tenantMetrics['failed_imports'][$tenantId] ?? 0,
                    'unverified_users' => $tenantMetrics['unverified_users'][$tenantId] ?? 0,
                    'admin_users' => $tenantMetrics['admin_users'][$tenantId] ?? 0,
                ];
                $tenant->admin_last_activity_at = $lastActivity[$tenantId] ?? null;
                $tenant->admin_health = $this->tenantHealth($tenant);
                $tenant->admin_profile = $this->tenantProfile($tenant);
                $tenant->admin_feature_state = $this->tenantFeatureState($tenant);
                $tenant->admin_registration_review = $this->tenantRegistrationReview($tenant);

                return $tenant;
            });

        $latestTenants = $allTenants->take(12);

        $attentionTenants = $allTenants
            ->filter(fn (Tenant $tenant) => ($tenant->admin_health['level'] ?? 'ok') !== 'ok' || ($tenant->admin_registration_review['level'] ?? 'ok') !== 'ok')
            ->take(8)
            ->values();

        $lifecycleStats = [
            'new_7_days' => Tenant::where('created_at', '>=', now()->subDays(7))->count(),
            'new_30_days' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
            'active_30_days' => collect($lastActivity)->filter(fn ($date) => $date?->greaterThanOrEqualTo(now()->subDays(30)))->count(),
            'silent_30_days' => $allTenants->filter(fn (Tenant $tenant) => ! $tenant->admin_last_activity_at || $tenant->admin_last_activity_at->lt(now()->subDays(30)))->count(),
            'with_members' => $allTenants->filter(fn (Tenant $tenant) => ($tenant->admin_metrics['members'] ?? 0) > 0)->count(),
            'with_events' => $allTenants->filter(fn (Tenant $tenant) => ($tenant->admin_metrics['events'] ?? 0) > 0)->count(),
            'with_location' => $allTenants->filter(fn (Tenant $tenant) => filled($tenant->city) || filled($tenant->zip))->count(),
            'with_imports' => $allTenants->filter(fn (Tenant $tenant) => ($tenant->admin_metrics['imports'] ?? 0) > 0)->count(),
            'support_ready' => $allTenants->filter(fn (Tenant $tenant) => ($tenant->admin_health['level'] ?? 'risk') === 'ok' && ($tenant->admin_registration_review['level'] ?? 'risk') === 'ok')->count(),
            'without_admin_user' => $allTenants->filter(fn (Tenant $tenant) => ($tenant->admin_metrics['admin_users'] ?? 0) === 0)->count(),
        ];

        $latestUsers = User::with('tenant')
            ->latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'totalTenants',
            'totalUsers',
            'platformStats',
            'lifecycleStats',
            'allTenants',
            'attentionTenants',
            'latestTenants',
            'latestUsers'
        ));
    }

    public function account(Request $request)
    {
        return view('admin.account', [
            'user' => $request->user(),
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user?->isSuperAdmin() || filled($user->tenant_id)) {
            throw ValidationException::withMessages([
                'email' => 'Dieses Konto ist kein Betreiberkonto.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => now(),
        ])->save();

        return redirect()
            ->route('admin.account')
            ->with('status', 'account-updated');
    }

    public function showTenant(Tenant $tenant)
    {
        $tenant->load(['users' => fn ($query) => $query->latest()]);

        $stats = [
            'members' => $this->tenantCount('members', $tenant),
            'active_members' => $this->tenantCount('members', $tenant, fn ($query) => $query->whereNull('archived_at')),
            'archived_members' => $this->tenantCount('members', $tenant, fn ($query) => $query->whereNotNull('archived_at')),
            'users' => $tenant->users->count(),
            'events' => $this->tenantCount('events', $tenant),
            'upcoming_events' => $this->tenantCount('events', $tenant, fn ($query) => $query->where('start', '>=', now())),
            'documents' => $this->tenantCount('documents', $tenant),
            'forms' => $this->tenantCount('public_forms', $tenant),
            'protocols' => $this->tenantCount('protocols', $tenant),
            'tasks' => $this->tenantCount('tasks', $tenant),
            'invitations' => $this->tenantCount('event_invitations', $tenant),
            'imports' => $this->tenantCount('import_runs', $tenant),
            'accounts' => $this->tenantCount('accounts', $tenant),
            'donations' => $this->tenantCount('donations', $tenant),
            'failed_imports' => $this->tenantCount('import_runs', $tenant, fn ($query) => $query->where('status', '!=', 'completed')),
            'admin_users' => $this->tenantCount('users', $tenant, fn ($query) => $query->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])),
            'unverified_users' => $this->tenantCount('users', $tenant, fn ($query) => $query->whereNull('email_verified_at')),
            'recent_logins_30_days' => $this->tenantCount('users', $tenant, fn ($query) => $query->where('last_login_at', '>=', now()->subDays(30))),
        ];

        $recentEvents = $this->tenantRows('events', $tenant, ['id', 'title', 'start', 'created_at'], 'start', 8);
        $recentDocuments = $this->tenantRows('documents', $tenant, ['id', 'title', 'category', 'created_at'], 'created_at', 6);
        $recentForms = $this->tenantRows('public_forms', $tenant, ['id', 'title', 'status', 'created_at'], 'created_at', 6);
        $recentImports = $this->tenantRows('import_runs', $tenant, ['id', 'import_type', 'filename', 'imported_count', 'skipped_count', 'created_at'], 'created_at', 6);
        $tenantProfile = $this->tenantProfile($tenant);
        $featureState = $this->tenantFeatureStateFromStats($tenant, $stats);
        $registrationReview = $this->tenantRegistrationReview($tenant, $stats);
        $lastActivity = $this->lastActivityByTenant([$tenant->id])[(int) $tenant->id] ?? null;
        $supportDossier = $this->supportDossier($tenant, $stats, $registrationReview, $lastActivity);

        return view('admin.tenants.show', compact(
            'tenant',
            'stats',
            'recentEvents',
            'recentDocuments',
            'recentForms',
            'recentImports',
            'tenantProfile',
            'featureState',
            'registrationReview',
            'lastActivity',
            'supportDossier'
        ));
    }

    public function updateVerification(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'verification_status' => ['required', Rule::in(['pending', 'verified', 'suspicious', 'rejected'])],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant->update([
            'verification_status' => $validated['verification_status'],
            'verification_notes' => $validated['verification_notes'] ?? null,
            'verified_at' => $validated['verification_status'] === 'verified' ? now() : null,
            'verified_by_user_id' => $validated['verification_status'] === 'verified' ? $request->user()->id : null,
        ]);

        return redirect()
            ->route('admin.tenants.show', $tenant)
            ->with('success', 'Vereinsprüfung wurde gespeichert.');
    }

    public function updateLicense(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'license_mode' => ['required', Rule::in(['standard', 'beta', 'gifted'])],
            'license_expires_at' => ['nullable', 'date'],
        ]);

        $tenant->update([
            'license_mode' => $validated['license_mode'],
            'license_expires_at' => in_array($validated['license_mode'], ['beta', 'gifted'], true)
                ? ($validated['license_expires_at'] ?? null)
                : null,
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', "Lizenz für {$tenant->name} wurde aktualisiert.");
    }

    public function destroyTenant(Request $request, Tenant $tenant): RedirectResponse
    {
        if ((string) $request->user()?->tenant_id === (string) $tenant->id) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Der eigene Verein kann hier nicht geloescht werden.');
        }

        $request->validate([
            'confirmation' => ['required', 'in:DELETE'],
        ]);

        $tenantName = $tenant->name;
        $userIds = $tenant->users()->pluck('id')->all();
        $userEmails = $tenant->users()->pluck('email')->filter()->all();
        $accountIds = DB::table('accounts')->where('tenant_id', $tenant->getKey())->pluck('id')->all();
        $storagePaths = array_filter([
            $tenant->logo_storage_path,
            $tenant->pdf_template,
        ]);

        DB::transaction(function () use ($tenant, $userIds, $userEmails, $accountIds) {
            if ($userIds !== []) {
                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            }

            if ($userEmails !== []) {
                DB::table('password_reset_tokens')->whereIn('email', $userEmails)->delete();
            }

            if ($accountIds !== []) {
                DB::table('transactions')
                    ->where(function ($query) use ($accountIds) {
                        if (Schema::hasColumn('transactions', 'account_id')) {
                            $query->whereIn('account_id', $accountIds);
                        }

                        if (Schema::hasColumn('transactions', 'account_from_id')) {
                            $query->orWhereIn('account_from_id', $accountIds);
                        }

                        if (Schema::hasColumn('transactions', 'account_to_id')) {
                            $query->orWhereIn('account_to_id', $accountIds);
                        }
                    })
                    ->delete();
            }

            foreach (Schema::getTableListing() as $table) {
                if (in_array($table, ['migrations', 'tenants', 'users', 'sessions', 'password_reset_tokens', 'transactions'], true)) {
                    continue;
                }

                if (Schema::hasColumn($table, 'tenant_id')) {
                    DB::table($table)->where('tenant_id', $tenant->getKey())->delete();
                }
            }

            if ($userIds !== []) {
                DB::table('users')->whereIn('id', $userIds)->delete();
            }

            DB::table('tenants')->where('id', $tenant->getKey())->delete();
        });

        foreach ($storagePaths as $path) {
            Storage::disk('public')->delete($path);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', "Verein {$tenantName} wurde geloescht.");
    }

    private function countTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    /**
     * @return array<int, int>
     */
    private function countByTenant(string $table, ?callable $callback = null): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return [];
        }

        $query = DB::table($table)
            ->select('tenant_id', DB::raw('count(*) as aggregate'))
            ->whereNotNull('tenant_id');

        if ($callback) {
            $callback($query);
        }

        return $query
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id')
            ->mapWithKeys(fn ($count, $tenantId) => [(int) $tenantId => (int) $count])
            ->all();
    }

    /**
     * @param array<int, int|string> $tenantIds
     * @return array<int, \Illuminate\Support\Carbon|null>
     */
    private function lastActivityByTenant(array $tenantIds): array
    {
        $activity = [];

        foreach (['users', 'members', 'events', 'documents', 'public_forms', 'protocols', 'tasks'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id') || ! Schema::hasColumn($table, 'updated_at')) {
                continue;
            }

            DB::table($table)
                ->select('tenant_id', DB::raw('max(updated_at) as last_activity_at'))
                ->whereIn('tenant_id', $tenantIds)
                ->groupBy('tenant_id')
                ->get()
                ->each(function ($row) use (&$activity) {
                    $tenantId = (int) $row->tenant_id;
                    $current = $activity[$tenantId] ?? null;
                    $next = $row->last_activity_at ? \Illuminate\Support\Carbon::parse($row->last_activity_at) : null;

                    if ($next && (! $current || $next->greaterThan($current))) {
                        $activity[$tenantId] = $next;
                    }
                });
        }

        return $activity;
    }

    private function tenantCount(string $table, Tenant $tenant, ?callable $callback = null): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        $query = DB::table($table)->where('tenant_id', $tenant->id);

        if ($callback) {
            $callback($query);
        }

        return $query->count();
    }

    private function tenantRows(string $table, Tenant $tenant, array $columns, string $orderBy, int $limit)
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return collect();
        }

        $availableColumns = collect($columns)
            ->filter(fn ($column) => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        if ($availableColumns === []) {
            $availableColumns = ['id'];
        }

        $query = DB::table($table)
            ->select($availableColumns)
            ->where('tenant_id', $tenant->id);

        if (Schema::hasColumn($table, $orderBy)) {
            $query->orderByDesc($orderBy);
        }

        return $query->limit($limit)->get();
    }

    /**
     * @return array{level: string, label: string, reason: string}
     */
    private function tenantHealth(Tenant $tenant): array
    {
        $metrics = $tenant->admin_metrics ?? [];
        $hasAccess = $tenant->hasComplimentaryAccess()
            || $tenant->subscribed('default')
            || $tenant->trial_ends_at?->isFuture();

        if (in_array($tenant->verification_status, ['suspicious', 'rejected'], true)) {
            return [
                'level' => 'risk',
                'label' => $tenant->verification_status_label,
                'reason' => 'Diese Registrierung wurde vom Betreiber markiert.',
            ];
        }

        if (! $hasAccess) {
            return [
                'level' => 'risk',
                'label' => 'Lizenz prüfen',
                'reason' => 'Kein aktiver Zugriff erkennbar.',
            ];
        }

        if (($metrics['users'] ?? 0) === 0) {
            return [
                'level' => 'risk',
                'label' => 'Kein Benutzer',
                'reason' => 'Der Verein hat noch keinen Login.',
            ];
        }

        if (($tenant->verification_status ?? 'pending') === 'pending') {
            return [
                'level' => 'watch',
                'label' => 'Prüfung offen',
                'reason' => 'Der Verein wurde noch nicht als echter Verein bestätigt.',
            ];
        }

        if (($metrics['members'] ?? 0) === 0 && $tenant->created_at?->lt(now()->subDays(7))) {
            return [
                'level' => 'watch',
                'label' => 'Onboarding offen',
                'reason' => 'Nach einer Woche sind noch keine Mitglieder angelegt.',
            ];
        }

        if (! $tenant->admin_last_activity_at || $tenant->admin_last_activity_at->lt(now()->subDays(30))) {
            return [
                'level' => 'watch',
                'label' => 'Still',
                'reason' => 'Seit 30 Tagen keine Aktivität erkennbar.',
            ];
        }

        return [
            'level' => 'ok',
            'label' => 'Gesund',
            'reason' => 'Zugriff und Nutzung wirken plausibel.',
        ];
    }

    /**
     * @return array{location: string, address: string, contact: string, phone: string, age: string}
     */
    private function tenantProfile(Tenant $tenant): array
    {
        $location = trim(collect([$tenant->zip, $tenant->city])->filter()->implode(' '));
        $addressParts = array_filter([
            $tenant->address,
            $location,
        ]);

        return [
            'location' => $location !== '' ? $location : 'Ort fehlt',
            'address' => $addressParts !== [] ? implode(', ', $addressParts) : 'Adresse fehlt',
            'contact' => $tenant->email ?: 'E-Mail fehlt',
            'phone' => $tenant->phone ?: 'Telefon fehlt',
            'age' => $tenant->created_at?->diffForHumans() ?? 'unbekannt',
        ];
    }

    /**
     * @return array<int, array{label: string, value: int|string, state: string}>
     */
    private function tenantFeatureState(Tenant $tenant): array
    {
        return $this->tenantFeatureStateFromStats($tenant, $tenant->admin_metrics ?? []);
    }

    /**
     * @param array<string, int> $stats
     * @return array<int, array{label: string, value: int|string, state: string}>
     */
    private function tenantFeatureStateFromStats(Tenant $tenant, array $stats): array
    {
        return [
            [
                'label' => 'Onboarding',
                'value' => ($stats['members'] ?? 0) > 0 ? 'gestartet' : 'offen',
                'state' => ($stats['members'] ?? 0) > 0 ? 'ok' : 'watch',
            ],
            [
                'label' => 'Kalender',
                'value' => (int) ($stats['events'] ?? 0),
                'state' => ($stats['events'] ?? 0) > 0 ? 'ok' : 'muted',
            ],
            [
                'label' => 'Dokumente',
                'value' => (int) ($stats['documents'] ?? 0),
                'state' => ($stats['documents'] ?? 0) > 0 ? 'ok' : 'muted',
            ],
            [
                'label' => 'Importe',
                'value' => (int) ($stats['imports'] ?? 0),
                'state' => ($stats['imports'] ?? 0) > 0 ? 'ok' : 'muted',
            ],
            [
                'label' => 'Finanzen',
                'value' => (int) ($stats['accounts'] ?? 0),
                'state' => ($stats['accounts'] ?? 0) > 0 ? 'ok' : 'muted',
            ],
            [
                'label' => 'Spenden',
                'value' => $tenant->donation_certificates_enabled ? 'aktiv' : 'aus',
                'state' => $tenant->donation_certificates_enabled ? 'ok' : 'muted',
            ],
        ];
    }

    /**
     * @param array<string, int>|null $stats
     * @return array{level: string, label: string, score: int, reasons: array<int, string>, positive: array<int, string>}
     */
    private function tenantRegistrationReview(Tenant $tenant, ?array $stats = null): array
    {
        $stats ??= $tenant->admin_metrics ?? [];
        $score = 0;
        $reasons = [];
        $positive = [];

        if (($tenant->verification_status ?? 'pending') === 'verified') {
            $positive[] = 'Vom Betreiber geprüft.';
        } elseif (in_array($tenant->verification_status, ['suspicious', 'rejected'], true)) {
            $score += 80;
            $reasons[] = 'Vom Betreiber als ' . Str::lower($tenant->verification_status_label) . ' markiert.';
        } else {
            $score += 15;
            $reasons[] = 'Prüfung durch Betreiber noch offen.';
        }

        if (blank($tenant->registration_contact_name)) {
            $score += 15;
            $reasons[] = 'Ansprechpartner fehlt.';
        } else {
            $positive[] = 'Ansprechpartner: ' . $tenant->registration_contact_name;
        }

        if (blank($tenant->registration_role)) {
            $score += 15;
            $reasons[] = 'Funktion im Verein fehlt.';
        } else {
            $positive[] = 'Funktion: ' . $tenant->registration_role;
        }

        if (blank($tenant->city) && blank($tenant->zip)) {
            $score += 12;
            $reasons[] = 'Ort fehlt.';
        } else {
            $positive[] = 'Ort vorhanden.';
        }

        if (blank($tenant->registration_website) && blank($tenant->register_number)) {
            $score += 12;
            $reasons[] = 'Kein externer Vereinsnachweis hinterlegt.';
        } else {
            $positive[] = 'Externer Nachweis vorhanden.';
        }

        if (($stats['members'] ?? 0) === 0 && ($stats['imports'] ?? 0) === 0 && $tenant->created_at?->lt(now()->subDays(3))) {
            $score += 10;
            $reasons[] = 'Nach drei Tagen noch keine Mitglieder oder Importe.';
        }

        if (($stats['tasks'] ?? 0) > 0 && ($stats['members'] ?? 0) === 0) {
            $score += 12;
            $reasons[] = 'Aufgaben angelegt, aber keine Mitglieder.';
        }

        if (($stats['accounts'] ?? 0) > 0 && ($stats['members'] ?? 0) === 0) {
            $score += 12;
            $reasons[] = 'Finanzen genutzt, aber keine Mitgliederbasis.';
        }

        if (($stats['members'] ?? 0) > 0 || ($stats['imports'] ?? 0) > 0) {
            $positive[] = 'Vereinsdaten wurden angelegt oder importiert.';
        }

        $level = match (true) {
            $score >= 55 => 'risk',
            $score >= 20 => 'watch',
            default => 'ok',
        };

        return [
            'level' => $level,
            'label' => match ($level) {
                'risk' => 'Genau prüfen',
                'watch' => 'Prüfung offen',
                default => 'Plausibel',
            },
            'score' => min(100, $score),
            'reasons' => $reasons,
            'positive' => $positive,
        ];
    }

    /**
     * @param array<string, int> $stats
     * @param array{level: string, label: string, score: int, reasons: array<int, string>, positive: array<int, string>} $registrationReview
     * @return array{privacy_note: string, readiness: array{level: string, label: string, reason: string}, checklist: array<int, array{label: string, value: string, state: string}>, signals: array<int, array{label: string, text: string, state: string}>}
     */
    private function supportDossier(Tenant $tenant, array $stats, array $registrationReview, ?\Illuminate\Support\Carbon $lastActivity): array
    {
        $hasAccess = $tenant->hasComplimentaryAccess()
            || $tenant->subscribed('default')
            || $tenant->trial_ends_at?->isFuture();

        $readinessLevel = match (true) {
            in_array($tenant->verification_status, ['suspicious', 'rejected'], true) => 'risk',
            ! $hasAccess => 'risk',
            ($stats['admin_users'] ?? 0) === 0 => 'risk',
            ($registrationReview['level'] ?? 'watch') !== 'ok' => 'watch',
            ! $lastActivity || $lastActivity->lt(now()->subDays(30)) => 'watch',
            default => 'ok',
        };

        $signals = [];

        if (($stats['admin_users'] ?? 0) === 0) {
            $signals[] = [
                'label' => 'Kein Vereinsadmin',
                'text' => 'Im Supportfall fehlt ein klarer Ansprechpartner mit Verwaltungsrechten.',
                'state' => 'risk',
            ];
        }

        if (($stats['unverified_users'] ?? 0) > 0) {
            $signals[] = [
                'label' => 'E-Mail-Bestätigung offen',
                'text' => ($stats['unverified_users'] ?? 0) . ' Benutzer haben ihre E-Mail noch nicht bestätigt.',
                'state' => 'watch',
            ];
        }

        if (($stats['failed_imports'] ?? 0) > 0) {
            $signals[] = [
                'label' => 'Import prüfen',
                'text' => ($stats['failed_imports'] ?? 0) . ' Importläufe sind nicht abgeschlossen.',
                'state' => 'watch',
            ];
        }

        if (($stats['members'] ?? 0) === 0 && ($stats['imports'] ?? 0) === 0) {
            $signals[] = [
                'label' => 'Onboarding offen',
                'text' => 'Noch keine Mitglieder oder Importe sichtbar.',
                'state' => 'watch',
            ];
        }

        if (! $lastActivity || $lastActivity->lt(now()->subDays(30))) {
            $signals[] = [
                'label' => 'Nutzung still',
                'text' => 'Seit 30 Tagen ist keine relevante Aktivität erkennbar.',
                'state' => 'watch',
            ];
        }

        if ($signals === []) {
            $signals[] = [
                'label' => 'Supportbereit',
                'text' => 'Die wichtigsten Betreiber-Signale wirken plausibel.',
                'state' => 'ok',
            ];
        }

        return [
            'privacy_note' => 'Supportsicht zeigt bewusst Metadaten, Statuswerte und technische Plausibilität. Inhalte aus Mitgliederdaten, Dokumenten, Protokollen oder Finanzen werden nicht geöffnet.',
            'readiness' => [
                'level' => $readinessLevel,
                'label' => match ($readinessLevel) {
                    'risk' => 'Support kritisch',
                    'watch' => 'Support vorbereiten',
                    default => 'Supportbereit',
                },
                'reason' => match ($readinessLevel) {
                    'risk' => 'Für einen Supportfall fehlen wichtige Grundlagen oder der Verein ist markiert.',
                    'watch' => 'Support ist möglich, aber einzelne Punkte sollten vorab geklärt werden.',
                    default => 'Kontakt, Lizenz, Nutzung und Plausibilität wirken belastbar.',
                },
            ],
            'checklist' => [
                [
                    'label' => 'Vereinsprüfung',
                    'value' => $tenant->verification_status_label,
                    'state' => ($registrationReview['level'] ?? 'watch') === 'ok' ? 'ok' : 'watch',
                ],
                [
                    'label' => 'Lizenz/Zugriff',
                    'value' => $hasAccess ? 'aktiv' : 'prüfen',
                    'state' => $hasAccess ? 'ok' : 'risk',
                ],
                [
                    'label' => 'Adminbenutzer',
                    'value' => (string) ($stats['admin_users'] ?? 0),
                    'state' => ($stats['admin_users'] ?? 0) > 0 ? 'ok' : 'risk',
                ],
                [
                    'label' => 'Logins 30 Tage',
                    'value' => (string) ($stats['recent_logins_30_days'] ?? 0),
                    'state' => ($stats['recent_logins_30_days'] ?? 0) > 0 ? 'ok' : 'watch',
                ],
                [
                    'label' => 'Datenbasis',
                    'value' => (($stats['members'] ?? 0) > 0 || ($stats['imports'] ?? 0) > 0) ? 'vorhanden' : 'offen',
                    'state' => (($stats['members'] ?? 0) > 0 || ($stats['imports'] ?? 0) > 0) ? 'ok' : 'watch',
                ],
                [
                    'label' => 'Importstatus',
                    'value' => ($stats['failed_imports'] ?? 0) > 0 ? ($stats['failed_imports'] . ' prüfen') : 'unauffällig',
                    'state' => ($stats['failed_imports'] ?? 0) > 0 ? 'watch' : 'ok',
                ],
            ],
            'signals' => $signals,
        ];
    }
}
