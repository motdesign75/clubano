<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Event;
use App\Models\Member;

class EventController extends Controller
{
    /**
     * Dashboard mit Mitgliedsdaten + Events
     */
    public function dashboardEvents()
    {
        $tenant = app('currentTenant');
        $tenantId = $tenant->id;
        $today = now();

        // Kommende Events (max. 5)
        $events = Event::where('tenant_id', $tenantId)
            ->whereDate('start', '>=', $today)
            ->orderBy('start')
            ->take(5)
            ->get();

        // Mitgliederzahl
        $membersCount = Member::where('tenant_id', $tenantId)->count();

        // Lizenztyp
        $licenseType = $tenant->license_type ?? 'Trial';

        // Eintritte im aktuellen Monat
        $entries = Member::where('tenant_id', $tenantId)
            ->whereMonth('entry_date', $today->month)
            ->get();

        // Austritte im aktuellen Monat
        $exits = Member::where('tenant_id', $tenantId)
            ->whereMonth('exit_date', $today->month)
            ->get();

        // Geburtstage im aktuellen Monat
        $birthdays = Member::where('tenant_id', $tenantId)
            ->whereMonth('birthday', $today->month)
            ->get();

        // Jubiläen (5, 10, 25, 50 Jahre)
        $anniversaries = Member::where('tenant_id', $tenantId)
            ->whereNotNull('entry_date')
            ->get()
            ->filter(function ($member) use ($today) {
                $years = $today->year - $member->entry_date->year;
                return in_array($years, [5, 10, 25, 50]) &&
                       $member->entry_date->format('m-d') === $today->format('m-d');
            });

        return view('dashboard', compact(
            'events',
            'tenant',
            'membersCount',
            'licenseType',
            'entries',
            'exits',
            'birthdays',
            'anniversaries'
        ));
    }

    /**
     * Alle Events anzeigen
     */
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        $events = Event::where('tenant_id', $tenantId)
            ->orderBy('start', 'desc')
            ->get();

        return view('events.index', compact('events'));
    }

    /**
     * Neues Event-Formular anzeigen
     */
    public function create()
    {
        return view('events.create');
    }

    /**
     * Event speichern
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'nullable|string|max:255',
            'start'       => 'required|date',
            'end'         => 'required|date|after_or_equal:start',
            'is_public'   => 'required|boolean',
        ]);

        Event::create([
            'tenant_id'   => Auth::user()->tenant_id,
            'title'       => $request->title,
            'description' => $request->description,
            'location'    => $request->location,
            'start'       => $request->start,
            'end'         => $request->end,
            'is_public'   => $request->is_public,
        ]);

        return redirect()->route('events.index')->with('success', 'Event wurde gespeichert.');
    }

    /**
     * Event-Formular zum Bearbeiten
     */
    public function edit(Event $event)
    {
        $this->authorizeEvent($event);
        return view('events.edit', compact('event'));
    }

    /**
     * Event aktualisieren
     */
    public function update(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'nullable|string|max:255',
            'start'       => 'required|date',
            'end'         => 'required|date|after_or_equal:start',
            'is_public'   => 'required|boolean',
        ]);

        $event->update($request->all());

        return redirect()->route('events.index')->with('success', 'Event aktualisiert.');
    }

    /**
     * Event löschen
     */
    public function destroy(Event $event)
    {
        $this->authorizeEvent($event);
        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event gelöscht.');
    }

    /**
     * Schutzfunktion für Events
     */
    private function authorizeEvent(Event $event)
    {
        if ($event->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }
    }
}
