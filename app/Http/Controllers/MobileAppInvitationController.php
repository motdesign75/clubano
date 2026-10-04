<?php

namespace App\Http\Controllers;

use App\Models\MobileAppUser;
use App\Services\MobileAppSyncService;
use Illuminate\Http\Request;

class MobileAppInvitationController extends Controller
{
    public function __construct(private readonly MobileAppSyncService $syncService)
    {
    }

    public function show(string $token)
    {
        $appUser = $this->appUserForToken($token);

        return view('mobile-app-invitations.show', compact('appUser'));
    }

    public function store(Request $request, string $token)
    {
        $appUser = $this->appUserForToken($token);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->syncService->activate($appUser, $validated['password']);

        return view('mobile-app-invitations.done', compact('appUser'));
    }

    private function appUserForToken(string $token): MobileAppUser
    {
        return MobileAppUser::query()
            ->with(['tenant', 'member'])
            ->where('invitation_token', $token)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
