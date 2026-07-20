<?php

namespace App\Http\Controllers;

use App\Models\EventCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventCategoryController extends Controller
{
    public function index()
    {
        $categories = EventCategory::query()
            ->withCount('events')
            ->orderBy('name')
            ->get();

        return view('events.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('event_categories', 'slug')->where('tenant_id', $tenantId),
            ],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        EventCategory::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug'] ?: $validated['name']),
            'color' => strtoupper($validated['color']),
        ]);

        return redirect()->route('event-categories.index')->with('success', 'Kategorie wurde angelegt.');
    }

    public function update(Request $request, EventCategory $eventCategory): RedirectResponse
    {
        $this->authorizeCategory($eventCategory);
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('event_categories', 'slug')->ignore($eventCategory->id)->where('tenant_id', $tenantId),
            ],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $eventCategory->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug'] ?: $validated['name']),
            'color' => strtoupper($validated['color']),
        ]);

        return redirect()->route('event-categories.index')->with('success', 'Kategorie wurde aktualisiert.');
    }

    public function destroy(EventCategory $eventCategory): RedirectResponse
    {
        $this->authorizeCategory($eventCategory);

        $eventCategory->events()->update(['category_id' => null]);
        $eventCategory->delete();

        return redirect()->route('event-categories.index')->with('success', 'Kategorie wurde gelöscht.');
    }

    protected function authorizeCategory(EventCategory $eventCategory): void
    {
        abort_unless((string) $eventCategory->tenant_id === (string) auth()->user()->tenant_id, 403);
    }
}
