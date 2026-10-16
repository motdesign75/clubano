<?php

namespace App\Http\Controllers;

use App\Models\MobileAppUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class MobilePasswordController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:120']]);
        $user = MobileAppUser::whereRaw('LOWER(username) = ?', [mb_strtolower(trim($data['username']))])
            ->where('is_active', true)->with('member')->first();

        if ($user && filled($user->member?->email) && ! $user->member->archived_at) {
            Password::broker('mobile')->sendResetLink(['username' => $user->username, 'is_active' => true]);
        }

        return response()->json(['message' => 'Wenn ein aktiver App-Zugang mit hinterlegter E-Mail-Adresse existiert, erhältst du einen Rücksetzlink.']);
    }

    public function show(Request $request, string $token)
    {
        return view('mobile-app-invitations.reset-password', ['token' => $token, 'username' => (string) $request->query('username')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $status = Password::broker('mobile')->reset($data + ['is_active' => true], function (MobileAppUser $user, string $password) {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['password' => 'Der Link ist ungültig oder abgelaufen. Bitte fordere in der App einen neuen Link an.']);
        }

        return back()->with('status', 'Dein App-Passwort wurde geändert. Du kannst dich jetzt in Mein Clubano anmelden.');
    }
}
