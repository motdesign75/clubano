<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailSettingsController extends Controller
{
    public function edit()
    {
        $tenant = auth()->user()->tenant;

        return view('settings.email', compact('tenant'));
    }

    public function update(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $request->validate([
            'mail_mailer' => 'nullable|string',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|string',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string',
        ]);

        $tenant->update([
            'mail_mailer' => $request->mail_mailer,
            'mail_host' => $request->mail_host,
            'mail_port' => $request->mail_port,
            'mail_username' => $request->mail_username,
            'mail_password' => $request->mail_password,
            'mail_encryption' => $request->mail_encryption,
            'mail_from_address' => $request->mail_from_address,
            'mail_from_name' => $request->mail_from_name,
        ]);

        return redirect()->route('settings.email.edit')->with('success', 'SMTP-Einstellungen gespeichert.');
    }
}
