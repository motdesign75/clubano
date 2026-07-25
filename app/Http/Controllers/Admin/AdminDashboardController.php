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
        ];

        $tenantMetrics = [
            'members' => $this->countByTenant('members'),
            'users' => $this->countByTenant('users'),
            'events' => $this->countByTenant('events'),
            'documents' => $this->countByTenant('documents'),
            'forms' => $this->countByTenant('public_forms'),
            'invitations' => $this->countByTenant('event_invitations'),
        ];

        $lastActivity = $this->lastActivityByTenant($tenantIds->all());

        $allTenants = Tenant::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Tenant $tenant) use ($tenantMetrics, $lastActivity) {
                $tenantId = (int) $tenant->id;
                $tenant->admin_metrics = [
                    'members' => $tenantMetrics['members'][$tenantId] ?? 0,
                    'users' => $tenantMetrics['users'][$tenantId] ?? 0,
                    'events' => $tenantMetrics['events'][$tenantId] ?? 0,
                    'documents' => $tenantMetrics['documents'][$tenantId] ?? 0,
                    'forms' => $tenantMetrics['forms'][$tenantId] ?? 0,
                    'invitations' => $tenantMetrics['invitations'][$tenantId] ?? 0,
                ];
                $tenant->admin_last_activity_at = $lastActivity[$tenantId] ?? null;
                $tenant->admin_health = $this->tenantHealth($tenant);

                return $tenant;
            });

        $latestTenants = $allTenants->take(12);

        $attentionTenants = $allTenants
            ->filter(fn (Tenant $tenant) => ($tenant->admin_health['level'] ?? 'ok') !== 'ok')
            ->take(8)
            ->values();

        $lifecycleStats = [
            'new_7_days' => Tenant::where('created_at', '>=', now()->subDays(7))->count(),
            'new_30_days' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
            'active_30_days' => collect($lastActivity)->filter(fn ($date) => $date?->greaterThanOrEqualTo(now()->subDays(30)))->count(),
            'silent_30_days' => $allTenants->filter(fn (Tenant $tenant) => ! $tenant->admin_last_activity_at || $tenant->admin_last_activity_at->lt(now()->subDays(30)))->count(),
            'with_members' => $allTenants->filter(fn (Tenant $tenant) => ($tenant->admin_metrics['members'] ?? 0) > 0)->count(),
            'with_events' => $allTenants->filter(fn (Tenant $tenant) => ($tenant->admin_metrics['events'] ?? 0) > 0)->count(),
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
        ];

        $recentEvents = $this->tenantRows('events', $tenant, ['id', 'title', 'start', 'created_at'], 'start', 8);
        $recentDocuments = $this->tenantRows('documents', $tenant, ['id', 'title', 'category', 'created_at'], 'created_at', 6);
        $recentForms = $this->tenantRows('public_forms', $tenant, ['id', 'title', 'status', 'created_at'], 'created_at', 6);

        return view('admin.tenants.show', compact(
            'tenant',
            'stats',
            'recentEvents',
            'recentDocuments',
            'recentForms'
        ));
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
    private function countByTenant(string $table): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return [];
        }

        return DB::table($table)
            ->select('tenant_id', DB::raw('count(*) as aggregate'))
            ->whereNotNull('tenant_id')
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
}
