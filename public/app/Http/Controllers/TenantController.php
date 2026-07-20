<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class TenantController extends Controller
{
    public function show(): View
    {
        $tenant = Auth::user()->tenant;

        // Achte darauf, dass die View tenant/show.blade.php heißt und layouts.sidebar erweitert
        return view('tenant.show', compact('tenant'));
    }

    public function edit(): View
    {
        $tenant = Auth::user()->tenant;

        return view('tenant.edit', compact('tenant'));
    }

    public function update(Request $request): RedirectResponse
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
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // altes Logo löschen, wenn vorhanden
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }

            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $tenant->update($validated);

        return redirect()->route('tenant.show')->with('success', 'Vereinsdaten wurden aktualisiert.');
    }
}
