<?php

namespace App\Http\Controllers;

use App\Models\EventCategory;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventCategoryController extends Controller
{
    public function index()
    {
        $categories = EventCategory::query()
            ->with('defaultTargetTag')
            ->withCount('events')
            ->orderBy('name')
            ->get();

        return view('events.categories.index', [
            'categories' => $categories,
            'targetTags' => Tag::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->orderBy('name')
                ->get(),
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
            'icon' => ['nullable', 'string', 'max:40'],
            'default_target_tag_id' => ['nullable', Rule::exists('tags', 'id')->where('tenant_id', $tenantId)],
            'default_visibility' => ['required', 'in:public,internal'],
            'attendance_enabled_default' => ['nullable', 'boolean'],
            'response_required_default' => ['nullable', 'boolean'],
            'counts_toward_required_hours_default' => ['nullable', 'boolean'],
            'reminders_enabled_default' => ['nullable', 'boolean'],
        ]);

        EventCategory::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug'] ?: $validated['name']),
            'color' => strtoupper($validated['color']),
            'icon' => $validated['icon'] ?: 'calendar',
            'default_target_tag_id' => $validated['default_target_tag_id'] ?? null,
            'default_visibility' => $validated['default_visibility'],
            'attendance_enabled_default' => $request->boolean('attendance_enabled_default'),
            'response_required_default' => $request->boolean('response_required_default'),
            'counts_toward_required_hours_default' => $request->boolean('counts_toward_required_hours_default'),
            'reminders_enabled_default' => $request->boolean('reminders_enabled_default'),
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
            'icon' => ['nullable', 'string', 'max:40'],
            'default_target_tag_id' => ['nullable', Rule::exists('tags', 'id')->where('tenant_id', $tenantId)],
            'default_visibility' => ['required', 'in:public,internal'],
            'attendance_enabled_default' => ['nullable', 'boolean'],
            'response_required_default' => ['nullable', 'boolean'],
            'counts_toward_required_hours_default' => ['nullable', 'boolean'],
            'reminders_enabled_default' => ['nullable', 'boolean'],
        ]);

        $eventCategory->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug'] ?: $validated['name']),
            'color' => strtoupper($validated['color']),
            'icon' => $validated['icon'] ?: 'calendar',
            'default_target_tag_id' => $validated['default_target_tag_id'] ?? null,
            'default_visibility' => $validated['default_visibility'],
            'attendance_enabled_default' => $request->boolean('attendance_enabled_default'),
            'response_required_default' => $request->boolean('response_required_default'),
            'counts_toward_required_hours_default' => $request->boolean('counts_toward_required_hours_default'),
            'reminders_enabled_default' => $request->boolean('reminders_enabled_default'),
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
