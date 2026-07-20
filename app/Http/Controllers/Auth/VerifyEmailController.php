<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::query()->find($request->route('id'));

        if (! $user) {
            return redirect()->route('login')->with('error', 'Der Bestätigungslink ist nicht mehr gültig.');
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if (! Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $user = $user->fresh();

        if ($user && $user->hasVerifiedEmail()) {
            $user->tenant?->startSelfServeTrialIfEligible();
            return redirect()->route('dashboard', ['verified' => 1]);
        }

        if ($user && $user->markEmailAsVerified()) {
            $user->fresh()->tenant?->startSelfServeTrialIfEligible();
            event(new Verified($user));
        }

        return redirect()->route('dashboard', ['verified' => 1]);
    }
}
