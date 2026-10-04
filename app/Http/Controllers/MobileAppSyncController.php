<?php

namespace App\Http\Controllers;

use App\Models\MobileAppUser;
use App\Models\Tag;
use App\Services\MobileAppSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileAppSyncController extends Controller
{
    public function __construct(private readonly MobileAppSyncService $syncService)
    {
    }

    public function index()
    {
        $tenant = auth()->user()->tenant;
        $tenant->load('mobileAppSyncTag');

        $tags = Tag::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        $stats = $this->stats($tenant->id);
        $eligibleCount = $this->syncService->eligibleMembers($tenant)->count();

        $appUsers = MobileAppUser::query()
            ->with('member')
            ->where('tenant_id', $tenant->id)
            ->latest('synced_at')
            ->latest()
            ->limit(80)
            ->get();

        return view('mobile-app-sync.index', compact('tenant', 'tags', 'stats', 'eligibleCount', 'appUsers'));
    }

    public function update(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'mobile_app_sync_enabled' => ['nullable', 'boolean'],
            'mobile_app_sync_tag_id' => [
                'nullable',
                'integer',
                Rule::exists('tags', 'id')->where('tenant_id', $tenant->id),
            ],
        ]);

        $tenant->forceFill([
            'mobile_app_sync_enabled' => $request->boolean('mobile_app_sync_enabled'),
            'mobile_app_sync_tag_id' => $validated['mobile_app_sync_tag_id'] ?? null,
        ])->save();

        return redirect()
            ->route('mobile-app-sync.index')
            ->with('success', 'App-Synchronisierung wurde gespeichert.');
    }

    public function run(Request $request)
    {
        $tenant = auth()->user()->tenant;

        if (! $tenant->mobile_app_sync_enabled) {
            return redirect()
                ->route('mobile-app-sync.index')
                ->with('error', 'Bitte aktiviere die Synchronisierung zuerst.');
        }

        $stats = $this->syncService->sync($tenant, $request->boolean('send_invitations', true));

        return redirect()
            ->route('mobile-app-sync.index')
            ->with('success', "Synchronisierung abgeschlossen: {$stats['synced']} synchronisiert, {$stats['invited']} eingeladen, {$stats['removed']} entfernt, {$stats['failed']} fehlgeschlagen.");
    }

    private function stats(int $tenantId): array
    {
        return [
            'synced' => MobileAppUser::query()->where('tenant_id', $tenantId)->whereNotNull('synced_at')->count(),
            'activated' => MobileAppUser::query()->where('tenant_id', $tenantId)->whereNotNull('accepted_at')->count(),
            'failed' => MobileAppUser::query()->where('tenant_id', $tenantId)->where('sync_status', MobileAppUser::STATUS_FAILED)->count(),
            'removed' => MobileAppUser::query()->where('tenant_id', $tenantId)->where('sync_status', MobileAppUser::STATUS_REMOVED)->count(),
            'ignored' => MobileAppUser::query()->where('tenant_id', $tenantId)->whereNotNull('sync_ignored_at')->count(),
        ];
    }
}
