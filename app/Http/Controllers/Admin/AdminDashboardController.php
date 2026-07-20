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

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalTenants = class_exists(Tenant::class) ? Tenant::count() : 0;
        $totalUsers = class_exists(User::class) ? User::count() : 0;

        $latestTenants = class_exists(Tenant::class)
            ? Tenant::latest()->take(10)->get()
            : collect();

        $latestUsers = class_exists(User::class)
            ? User::latest()->take(5)->get()
            : collect();

        return view('admin.dashboard', compact(
            'totalTenants',
            'totalUsers',
            'latestTenants',
            'latestUsers'
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
}
