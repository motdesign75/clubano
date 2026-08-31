<?php

namespace App\Http\Controllers;

use App\Models\InvitationCode;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantController extends Controller
{
    public function show(): View
    {
        $tenant = Auth::user()->tenant;
        return view('tenant.show', compact('tenant'));
    }

    public function edit(): View
    {
        $tenant = Auth::user()->tenant;
        return view('tenant.edit', compact('tenant'));
    }

    public function update(Request $request, HtmlSanitizer $htmlSanitizer): RedirectResponse
    {
        $tenant = Auth::user()->tenant;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,slug,' . $tenant->id,
            'email' => 'required|email',
            'address' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'register_number' => 'nullable|string|max:255',
            'creditor_identifier' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',

            // Neue Felder
            'iban' => 'nullable|string|max:255',
            'bic' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'chairman_name' => 'nullable|string|max:255',
            'pdf_template' => 'nullable|mimes:pdf,jpg,jpeg,png|max:2048',
            'use_letterhead' => 'nullable|boolean',
            'member_exit_mail_enabled' => 'nullable|boolean',
            'member_exit_mail_subject' => 'nullable|string|max:255',
            'member_exit_mail_body' => 'nullable|string',
        ]);

        // Logo speichern
        if ($request->hasFile('logo')) {
            if ($tenant->logo_storage_path) {
                Storage::disk('public')->delete($tenant->logo_storage_path);
            }
            $storedLogo = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $storedLogo;
            $validated['logo_path'] = $storedLogo;
        }

        // PDF-Briefbogen speichern
        if ($request->hasFile('pdf_template')) {
            if ($tenant->pdf_template) {
                Storage::disk('public')->delete($tenant->pdf_template);
            }
            $validated['pdf_template'] = $request->file('pdf_template')->store('briefbogen', 'public');
        }

        // Checkbox-Wert setzen
        $validated['use_letterhead'] = $request->has('use_letterhead');
        $validated['member_exit_mail_enabled'] = $request->boolean('member_exit_mail_enabled');

        $validated['member_exit_mail_subject'] = trim((string) ($validated['member_exit_mail_subject'] ?? ''));
        $validated['member_exit_mail_body'] = trim((string) ($validated['member_exit_mail_body'] ?? ''));

        if (! $validated['member_exit_mail_enabled']) {
            $validated['member_exit_mail_subject'] = null;
            $validated['member_exit_mail_body'] = null;
        } else {
            if ($validated['member_exit_mail_subject'] === '') {
                $validated['member_exit_mail_subject'] = 'Bestaetigung deines Austritts bei {verein}';
            }

            if ($validated['member_exit_mail_body'] === '') {
                $validated['member_exit_mail_body'] = '<p>{anrede},</p><p>wir bestaetigen dir hiermit deinen Austritt aus <strong>{verein}</strong> zum <strong>{austrittsdatum}</strong>.</p><p>Danke fuer die gemeinsame Zeit und alles, was du eingebracht hast.</p><p>Wenn noch etwas offen ist, melde dich einfach direkt bei uns.</p><p>Herzliche Gruesse<br>{verein}</p>';
            }

            $validated['member_exit_mail_body'] = $htmlSanitizer->sanitize($validated['member_exit_mail_body']);
        }

        // Update durchführen
        $tenant->update($validated);

        // Prüfen, ob bereits ein Einladungscode existiert
        if (!$tenant->invitationCode) {
            InvitationCode::create([
                'tenant_id' => $tenant->id,
                'code' => strtoupper(Str::uuid()),
            ]);
        }

        return redirect()->route('tenant.show')->with('success', 'Vereinsdaten wurden aktualisiert.');
    }

    public function logo(): StreamedResponse
    {
        $tenant = Auth::user()->tenant;

        abort_unless($tenant?->logo_storage_path, 404);
        abort_unless(Storage::disk('public')->exists($tenant->logo_storage_path), 404);

        return Storage::disk('public')->response($tenant->logo_storage_path);
    }

    public function letterhead(): StreamedResponse
    {
        $tenant = Auth::user()->tenant;

        abort_unless($tenant?->pdf_template, 404);
        abort_unless(Storage::disk('public')->exists($tenant->pdf_template), 404);

        return Storage::disk('public')->response($tenant->pdf_template);
    }
}
