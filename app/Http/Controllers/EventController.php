<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventChangeLog;
use App\Models\EventCategory;
use App\Models\EventShift;
use App\Models\EventShiftAssignment;
use App\Models\Document;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();
        $onboarding = app(OnboardingService::class)->buildForTenant($tenant);

        // Kommende Events (max. 5)
        $events = Event::where('tenant_id', $tenantId)
            ->whereDate('start', '>=', $today)
            ->orderBy('start')
            ->take(5)
            ->get();

        // Timeline: Alle Events in den nächsten 7 Tagen
        $timeline = $events->filter(function ($event) {
            return \Carbon\Carbon::parse($event->start)->isBetween(now(), now()->addDays(7));
        });

        $membersBaseQuery = Member::where('tenant_id', $tenantId)
            ->whereNull('archived_at');

        // Mitgliederzahl
        $membersCount = (clone $membersBaseQuery)->count();

        // Lizenztyp
        $licenseType = $tenant->license_type ?? 'Trial';

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        // Eintritte im aktuellen Monat
        $entries = (clone $membersBaseQuery)
            ->whereNotNull('entry_date')
            ->whereBetween('entry_date', [$monthStart, $monthEnd])
            ->get();

        $entriesThisYearCount = (clone $membersBaseQuery)
            ->whereBetween('entry_date', [$yearStart, $yearEnd])
            ->count();

        $upcomingEventsCount = Event::where('tenant_id', $tenantId)
            ->whereDate('start', '>=', $today)
            ->count();

        $publicEventsCount = Event::where('tenant_id', $tenantId)
            ->where('is_public', true)
            ->whereDate('start', '>=', $today)
            ->count();

        $formsCount = PublicForm::where('tenant_id', $tenantId)->count();

        $documentsCount = Document::where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->count();

        $documentAttentionCount = Document::where('tenant_id', $tenantId)
            ->needsAttention()
            ->count();

        // Austritte im aktuellen Monat
        $exits = (clone $membersBaseQuery)
            ->whereNotNull('exit_date')
            ->whereBetween('exit_date', [$monthStart, $monthEnd])
            ->get();

        // Geburtstage im aktuellen Monat
        $birthdays = (clone $membersBaseQuery)
            ->whereNotNull('birthday')
            ->whereMonth('birthday', $today->month)
            ->get();

        // Jubiläen (5, 10, 25, 50 Jahre)
        $anniversaries = (clone $membersBaseQuery)
            ->whereNotNull('entry_date')
            ->get()
            ->filter(function ($member) use ($today) {
                $years = $today->year - $member->entry_date->year;
                return in_array($years, [5, 10, 25, 50]) &&
                       $member->entry_date->format('m-d') === $today->format('m-d');
            });

        return view('dashboard', compact(
            'events',
            'timeline',
            'tenant',
            'membersCount',
            'licenseType',
            'entries',
            'entriesThisYearCount',
            'exits',
            'birthdays',
            'anniversaries',
            'onboarding',
            'upcomingEventsCount',
            'publicEventsCount',
            'formsCount',
            'documentsCount',
            'documentAttentionCount'
        ));
    }

    /**
     * Alle Events anzeigen
     */
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        $view = request('view', 'month');
        $month = request('month');
        $day = request('day');
        $year = request('year');
        $categoryId = request('category_id');
        $responsibleId = request('responsible_user_id');
        $conflictsOnly = request()->boolean('conflicts_only');
        $allowedViews = ['day', 'month', 'year'];

        if (! in_array($view, $allowedViews, true)) {
            $view = 'month';
        }

        $calendarMonth = $month
            ? \Carbon\Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth()
            : now()->startOfMonth();
        $calendarDay = $day
            ? \Carbon\Carbon::createFromFormat('Y-m-d', $day)->startOfDay()
            : now()->startOfDay();
        $calendarYear = $year
            ? \Carbon\Carbon::createFromFormat('Y-m-d', $year . '-01-01')->startOfYear()
            : now()->startOfYear();

        [$rangeStart, $rangeEnd] = match ($view) {
            'day' => [$calendarDay->copy()->startOfDay(), $calendarDay->copy()->endOfDay()],
            'year' => [$calendarYear->copy()->startOfYear(), $calendarYear->copy()->endOfYear()],
            default => [
                $calendarMonth->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY),
                $calendarMonth->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY),
            ],
        };

        $events = Event::where('tenant_id', $tenantId)
            ->with(['activeBookingForm', 'category', 'responsibleUser', 'creator', 'updater'])
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query->whereBetween('start', [$rangeStart, $rangeEnd])
                    ->orWhereBetween('end', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($subQuery) use ($rangeStart, $rangeEnd) {
                        $subQuery->where('start', '<=', $rangeStart)
                            ->where('end', '>=', $rangeEnd);
                    });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($responsibleId, fn ($query) => $query->where('responsible_user_id', $responsibleId))
            ->orderBy('start')
            ->get();

        $events = $this->attachConflictState($events);

        if ($conflictsOnly) {
            $events = $events->filter(fn (Event $event) => ($event->conflict_count ?? 0) > 0)->values();
        }

        $calendarDays = collect();
        $calendarYearMonths = collect();
        $cursor = $rangeStart->copy();

        while ($view === 'month' && $cursor <= $rangeEnd) {
            $daySlot = $cursor->copy();
            $calendarDays->push([
                'date' => $daySlot,
                'events' => $events->filter(fn (Event $event) => $event->start->isSameDay($daySlot))->values(),
                'isCurrentMonth' => $daySlot->month === $calendarMonth->month,
                'isToday' => $daySlot->isToday(),
            ]);

            $cursor->addDay();
        }

        if ($view === 'year') {
            $monthCursor = $calendarYear->copy()->startOfYear();

            while ($monthCursor->year === $calendarYear->year) {
                $monthStart = $monthCursor->copy()->startOfMonth();
                $monthEnd = $monthCursor->copy()->endOfMonth();

                $calendarYearMonths->push([
                    'label' => $monthCursor->translatedFormat('F'),
                    'month' => $monthCursor->format('Y-m'),
                    'events' => $events->filter(function (Event $event) use ($monthStart, $monthEnd) {
                        return $event->start <= $monthEnd && $event->end >= $monthStart;
                    })->values(),
                ]);

                $monthCursor->addMonth();
            }
        }

        $dayEvents = $view === 'day'
            ? $events->filter(fn (Event $event) => $event->start->isSameDay($calendarDay))->values()
            : collect();

        return view('events.index', [
            'calendarView' => $view,
            'events' => $events,
            'calendarDays' => $calendarDays,
            'calendarDay' => $calendarDay,
            'calendarMonth' => $calendarMonth,
            'calendarYear' => $calendarYear,
            'calendarYearMonths' => $calendarYearMonths,
            'dayEvents' => $dayEvents,
            'categories' => EventCategory::query()->orderBy('name')->get(),
            'users' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'filters' => [
                'category_id' => $categoryId,
                'responsible_user_id' => $responsibleId,
                'conflicts_only' => $conflictsOnly,
                'month' => $calendarMonth->format('Y-m'),
                'day' => $calendarDay->format('Y-m-d'),
                'year' => $calendarYear->format('Y'),
            ],
        ]);
    }

    /**
     * Neues Event-Formular anzeigen
     */
    public function create()
    {
        return view('events.create', [
            'event' => new Event([
                'is_public' => true,
                'booking_enabled' => false,
            ]),
            'categories' => EventCategory::query()->orderBy('name')->get(),
            'users' => User::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
        ]);
    }

    /**
     * Event speichern
     */
    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'nullable|string|max:255',
            'start'       => 'required|date',
            'end'         => 'required|date|after_or_equal:start',
            'category_id' => ['nullable', Rule::exists('event_categories', 'id')->where('tenant_id', $tenantId)],
            'responsible_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'is_public'   => 'required|boolean',
            'booking_enabled' => 'nullable|boolean',
            'price_per_person' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants_per_booking' => 'nullable|integer|min:1|max:50',
            'image'       => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('events', 'public');
        }

        $event = Event::create([
            'tenant_id'   => Auth::user()->tenant_id,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location'    => $validated['location'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'] ?? null,
            'start'       => $validated['start'],
            'end'         => $validated['end'],
            'is_public'   => (bool) $validated['is_public'],
            'created_by'  => Auth::id(),
            'updated_by'  => Auth::id(),
            'booking_enabled' => $request->boolean('booking_enabled'),
            'price_per_person' => $request->boolean('booking_enabled') ? ($validated['price_per_person'] ?? 0) : 0,
            'currency' => strtoupper($validated['currency'] ?? 'EUR'),
            'max_participants_per_booking' => $validated['max_participants_per_booking'] ?? 1,
            'image_path'  => $validated['image_path'] ?? null,
        ]);

        $this->syncBookingForm($event);
        $this->logEventChange($event, 'created', null, $event->fresh()->toArray(), 'Termin angelegt');

        return redirect()->route('events.edit', $event)->with('success', 'Event wurde gespeichert.');
    }

    /**
     * Event-Formular zum Bearbeiten
     */
    public function edit(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['activeBookingForm.fields', 'shifts.assignments.member', 'category', 'tenant', 'responsibleUser', 'creator', 'updater', 'changeLogs.user']);
        $event->setAttribute('conflicting_events', $this->findConflictingEvents($event));
        $event->setAttribute('conflict_count', $event->conflicting_events->count());

        return view('events.edit', [
            'event' => $event,
            'categories' => EventCategory::query()->orderBy('name')->get(),
            'users' => User::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
            ...$this->participantViewData($event),
            ...$this->shiftViewData($event),
        ]);
    }

    /**
     * Event aktualisieren
     */
    public function update(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'nullable|string|max:255',
            'start'       => 'required|date',
            'end'         => 'required|date|after_or_equal:start',
            'category_id' => ['nullable', Rule::exists('event_categories', 'id')->where('tenant_id', $tenantId)],
            'responsible_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'is_public'   => 'required|boolean',
            'booking_enabled' => 'nullable|boolean',
            'price_per_person' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants_per_booking' => 'nullable|integer|min:1|max:50',
            'image'       => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            if ($event->image_path) {
                Storage::disk('public')->delete($event->image_path);
            }

            $validated['image_path'] = $request->file('image')->store('events', 'public');
        }

        if ($request->boolean('remove_image') && $event->image_path) {
            Storage::disk('public')->delete($event->image_path);
            $validated['image_path'] = null;
        }

        $before = $event->fresh()->toArray();

        $event->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'] ?? null,
            'start' => $validated['start'],
            'end' => $validated['end'],
            'is_public' => (bool) $validated['is_public'],
            'updated_by' => Auth::id(),
            'booking_enabled' => $request->boolean('booking_enabled'),
            'price_per_person' => $request->boolean('booking_enabled') ? ($validated['price_per_person'] ?? 0) : 0,
            'currency' => strtoupper($validated['currency'] ?? ($event->currency ?: 'EUR')),
            'max_participants_per_booking' => $validated['max_participants_per_booking'] ?? ($event->max_participants_per_booking ?: 1),
            'image_path' => $validated['image_path'] ?? $event->image_path,
        ]);

        $this->syncBookingForm($event);
        $this->logEventChange($event, 'updated', $before, $event->fresh()->toArray(), $this->buildUpdateSummary($before, $event->fresh()->toArray()));

        return redirect()->route('events.edit', $event)->with('success', 'Event aktualisiert.');
    }

    public function show(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant', 'activeBookingForm.fields', 'shifts.assignments.member', 'category', 'responsibleUser', 'creator', 'updater', 'changeLogs.user']);
        $event->setAttribute('conflicting_events', $this->findConflictingEvents($event));
        $event->setAttribute('conflict_count', $event->conflicting_events->count());

        return view('events.show', [
            'event' => $event,
            'isPublicPreview' => false,
            ...$this->participantViewData($event),
            ...$this->shiftViewData($event),
        ]);
    }

    public function publicShow(int $eventId)
    {
        $event = Event::withoutGlobalScopes()
            ->with(['tenant', 'activeBookingForm', 'category'])
            ->where('id', $eventId)
            ->where('is_public', true)
            ->firstOrFail();

        return view('events.public-show', [
            'event' => $event,
            'isPublicPreview' => true,
        ]);
    }

    public function publicList(string $tenantSlug, Request $request)
    {
        $data = $this->publicListData($tenantSlug, $request);

        return view('events.public-index', $data + ['isEmbed' => false]);
    }

    public function publicEmbed(string $tenantSlug, Request $request)
    {
        $data = $this->publicListData($tenantSlug, $request);

        return response()
            ->view('events.public-index', $data + ['isEmbed' => true])
            ->header('Content-Security-Policy', "frame-ancestors *")
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function participantsExport(Event $event): StreamedResponse
    {
        $this->authorizeEvent($event);
        $event->load(['activeBookingForm.fields']);

        abort_unless($event->activeBookingForm, 404, 'Kein Buchungsformular vorhanden.');

        $bookings = $event->bookings()->with('participants')->get();
        $fieldLabels = $event->activeBookingForm->fields
            ->pluck('label', 'slug');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="event-' . $event->id . '-teilnehmer.csv"',
        ];

        $columns = ['buchung', 'buchender', 'email', 'telefon', 'teilnehmer_anzahl', 'preis_pro_person', 'gesamtbetrag', 'zahlstatus', 'buchungsstatus', 'eingegangen_am'];
        foreach ($fieldLabels as $slug => $label) {
            if (in_array($slug, ['first_name', 'last_name', 'full_name', 'email', 'phone', 'mobile', 'participant_count', 'participant_notes'], true)) {
                continue;
            }

            $columns[] = $label;
        }
        $columns[] = 'teilnehmer';

        $callback = function () use ($columns, $bookings, $fieldLabels) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns, ';');

            foreach ($bookings as $booking) {
                $submission = $booking->submission;
                $row = [
                    $booking->booking_reference,
                    $booking->booker_name,
                    $booking->booker_email,
                    $booking->booker_phone,
                    $booking->participant_count,
                    number_format((float) $booking->price_per_person, 2, '.', ''),
                    number_format((float) $booking->total_amount, 2, '.', ''),
                    $booking->payment_status,
                    $booking->booking_status,
                    $booking->created_at->format('Y-m-d H:i:s'),
                ];

                foreach ($fieldLabels as $slug => $label) {
                    if (in_array($slug, ['first_name', 'last_name', 'full_name', 'email', 'phone', 'mobile', 'participant_count', 'participant_notes'], true)) {
                        continue;
                    }

                    $value = $submission?->answers[$slug] ?? null;

                    if (is_bool($value)) {
                        $value = $value ? 'Ja' : 'Nein';
                    } elseif (is_array($value)) {
                        $value = implode(', ', $value);
                    }

                    $row[] = $value;
                }

                $row[] = $booking->participants
                    ->map(fn ($participant) => $participant->full_name)
                    ->implode(', ');

                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function updateBooking(Request $request, Event $event, EventBooking $booking)
    {
        $this->authorizeEvent($event);
        $this->authorizeBooking($event, $booking);

        $validated = $request->validate([
            'booking_status' => 'required|in:pending,confirmed,cancelled',
            'payment_status' => 'required|in:not_required,open,paid,cancelled',
        ]);

        if ((float) $booking->price_per_person <= 0) {
            $validated['payment_status'] = 'not_required';
        }

        $booking->update([
            'booking_status' => $validated['booking_status'],
            'payment_status' => $validated['payment_status'],
        ]);

        return redirect()
            ->route('events.edit', $event)
            ->with('success', 'Buchungs- und Zahlungsstatus wurden aktualisiert.');
    }

    public function schedulePrint(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant', 'shifts.assignments.member']);

        return view('events.schedule-print', [
            'event' => $event,
            ...$this->shiftViewData($event),
        ]);
    }

    public function schedulePdf(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant', 'shifts.assignments.member']);

        $pdf = Pdf::loadView('pdf.event-schedule', [
            'event' => $event,
            ...$this->shiftViewData($event),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('dienstplan-' . Str::slug($event->title ?: 'event') . '.pdf');
    }

    public function scheduleMemberPdf(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant', 'shifts.assignments.member']);

        $pdf = Pdf::loadView('pdf.event-schedule-members', [
            'event' => $event,
            ...$this->shiftViewData($event),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('dienstplan-aushang-' . Str::slug($event->title ?: 'event') . '.pdf');
    }

    public function scheduleExport(Event $event): StreamedResponse
    {
        $this->authorizeEvent($event);
        $event->load(['shifts.assignments.member']);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="event-' . $event->id . '-dienstplan.csv"',
        ];

        $callback = function () use ($event) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'schicht',
                'rolle',
                'beginn',
                'ende',
                'bedarf',
                'bestaetigt',
                'offen',
                'status',
                'helfer',
                'email',
                'telefon',
                'zuordnungsstatus',
                'notiz',
            ], ';');

            foreach ($event->shifts as $shift) {
                $assignments = $shift->assignments;

                if ($assignments->isEmpty()) {
                    fputcsv($handle, [
                        $shift->title,
                        $shift->role,
                        $shift->starts_at?->format('Y-m-d H:i'),
                        $shift->ends_at?->format('Y-m-d H:i'),
                        $shift->required_people,
                        $shift->confirmed_assignments_count,
                        $shift->open_slots,
                        $shift->coverage_status,
                        '',
                        '',
                        '',
                        '',
                        $shift->notes,
                    ], ';');

                    continue;
                }

                foreach ($assignments as $assignment) {
                    fputcsv($handle, [
                        $shift->title,
                        $shift->role,
                        $shift->starts_at?->format('Y-m-d H:i'),
                        $shift->ends_at?->format('Y-m-d H:i'),
                        $shift->required_people,
                        $shift->confirmed_assignments_count,
                        $shift->open_slots,
                        $shift->coverage_status,
                        $assignment->display_name,
                        $assignment->member?->email ?: $assignment->helper_email,
                        $assignment->member?->mobile ?: $assignment->helper_phone,
                        $assignment->status,
                        $assignment->notes ?: $shift->notes,
                    ], ';');
                }
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function storeShift(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'required_people' => 'required|integer|min:1|max:999',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'notes' => 'nullable|string|max:4000',
        ]);

        $event->shifts()->create([
            'tenant_id' => $event->tenant_id,
            'title' => $validated['title'],
            'role' => $validated['role'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'required_people' => $validated['required_people'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('events.edit', $event)->with('success', 'Schicht wurde angelegt.');
    }

    public function updateShift(Request $request, Event $event, EventShift $shift)
    {
        $this->authorizeEvent($event);
        $this->authorizeShift($event, $shift);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'required_people' => 'required|integer|min:1|max:999',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'notes' => 'nullable|string|max:4000',
        ]);

        $shift->update($validated);

        return redirect()->route('events.edit', $event)->with('success', 'Schicht wurde aktualisiert.');
    }

    public function destroyShift(Event $event, EventShift $shift)
    {
        $this->authorizeEvent($event);
        $this->authorizeShift($event, $shift);

        $shift->delete();

        return redirect()->route('events.edit', $event)->with('success', 'Schicht wurde gelöscht.');
    }

    public function storeShiftAssignment(Request $request, Event $event, EventShift $shift)
    {
        $this->authorizeEvent($event);
        $this->authorizeShift($event, $shift);

        $validated = $request->validate([
            'member_id' => 'nullable|exists:members,id',
            'helper_name' => 'nullable|string|max:255',
            'helper_email' => 'nullable|email|max:255',
            'helper_phone' => 'nullable|string|max:255',
            'status' => 'required|in:planned,confirmed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (blank($validated['member_id'] ?? null) && blank($validated['helper_name'] ?? null)) {
            return redirect()->route('events.edit', $event)->withErrors([
                'helper_name' => 'Bitte Mitglied oder Helfername angeben.',
            ]);
        }

        if (!blank($validated['member_id'] ?? null)) {
            $member = Member::query()
                ->where('tenant_id', $event->tenant_id)
                ->findOrFail($validated['member_id']);

            $alreadyAssigned = $shift->assignments()
                ->where('member_id', $member->id)
                ->where('status', '!=', 'cancelled')
                ->exists();

            if ($alreadyAssigned) {
                return redirect()->route('events.edit', $event)->withErrors([
                    'member_id' => 'Dieses Mitglied ist dieser Schicht bereits zugeordnet.',
                ]);
            }
        }

        $shift->assignments()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'member_id' => $validated['member_id'] ?? null,
            'helper_name' => $validated['helper_name'] ?? null,
            'helper_email' => $validated['helper_email'] ?? null,
            'helper_phone' => $validated['helper_phone'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('events.edit', $event)->with('success', 'Helferzuordnung wurde gespeichert.');
    }

    public function destroyShiftAssignment(Event $event, EventShift $shift, EventShiftAssignment $assignment)
    {
        $this->authorizeEvent($event);
        $this->authorizeShift($event, $shift);

        abort_unless(
            (int) $assignment->event_shift_id === (int) $shift->id && (int) $assignment->event_id === (int) $event->id,
            404
        );

        $assignment->delete();

        return redirect()->route('events.edit', $event)->with('success', 'Zuordnung wurde entfernt.');
    }

    public function image(int $eventId): StreamedResponse
    {
        $event = Event::withoutGlobalScopes()->findOrFail($eventId);

        $isAllowedForTenant = Auth::check() && (string) Auth::user()->tenant_id === (string) $event->tenant_id;
        abort_unless($event->is_public || $isAllowedForTenant, 404);
        abort_unless($event->image_path && Storage::disk('public')->exists($event->image_path), 404);

        return Storage::disk('public')->response($event->image_path);
    }

    /**
     * Event löschen
     */
    public function destroy(Event $event)
    {
        $this->authorizeEvent($event);
        $before = $event->fresh()->toArray();
        $this->logEventChange($event, 'deleted', $before, null, 'Termin geloescht');

        if ($event->image_path) {
            Storage::disk('public')->delete($event->image_path);
        }

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

    private function authorizeShift(Event $event, EventShift $shift): void
    {
        abort_unless(
            (int) $shift->event_id === (int) $event->id && (int) $shift->tenant_id === (int) $event->tenant_id,
            404
        );
    }

    private function authorizeBooking(Event $event, EventBooking $booking): void
    {
        abort_unless(
            (int) $booking->event_id === (int) $event->id && (int) $booking->tenant_id === (int) $event->tenant_id,
            404
        );
    }

    private function syncBookingForm(Event $event): void
    {
        $existingForms = PublicForm::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('form_type', 'event');

        if (!$event->booking_enabled) {
            $existingForms->update(['is_active' => false]);
            return;
        }

        $form = $existingForms->first();

        $slugBase = Str::slug($event->title ?: 'event-buchung');
        $slug = 'event-' . $event->id . '-' . $slugBase;

        if (!$form) {
            $form = PublicForm::create([
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'title' => 'Anmeldung: ' . $event->title,
                'slug' => $slug,
                'description' => 'Melde dich hier verbindlich zur Veranstaltung an.',
                'form_type' => 'event',
                'success_message' => 'Danke fuer deine Anmeldung. Wir haben deinen Platz vorgemerkt.',
                'is_active' => true,
            ]);
        } else {
            $form->update([
                'title' => 'Anmeldung: ' . $event->title,
                'slug' => $slug,
                'description' => $form->description ?: 'Melde dich hier verbindlich zur Veranstaltung an.',
                'is_active' => true,
            ]);
        }

        $form->update([
            'description' => $this->eventBookingDescription($event),
            'success_message' => $event->is_paid
                ? 'Danke fuer die Anmeldung. Die Rechnung mit der Bitte um Ueberweisung wurde per E-Mail versendet.'
                : 'Danke für die Anmeldung. Wir haben euren Platz vorgemerkt.',
        ]);

        $requiredFields = [
            ['label' => 'Vorname Ansprechpartner', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true, 'help_text' => 'Vorname der buchenden Person.', 'placeholder' => 'Vorname'],
            ['label' => 'Nachname Ansprechpartner', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true, 'help_text' => 'Nachname der buchenden Person.', 'placeholder' => 'Nachname'],
            ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'help_text' => 'An diese Adresse senden wir Bestätigung und weitere Infos.', 'placeholder' => 'name@beispiel.de'],
            ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false, 'help_text' => 'Optional für Rückfragen.', 'placeholder' => 'Telefonnummer'],
            ['label' => 'Strasse und Hausnummer', 'slug' => 'street', 'field_type' => 'text', 'is_required' => $event->is_paid, 'help_text' => 'Bei kostenpflichtigen Events fuer die Rechnungsadresse.', 'placeholder' => 'Musterstrasse 12'],
            ['label' => 'PLZ', 'slug' => 'zip', 'field_type' => 'text', 'is_required' => $event->is_paid, 'help_text' => 'Bei kostenpflichtigen Events fuer die Rechnungsadresse.', 'placeholder' => '12345'],
            ['label' => 'Ort', 'slug' => 'city', 'field_type' => 'text', 'is_required' => $event->is_paid, 'help_text' => 'Bei kostenpflichtigen Events fuer die Rechnungsadresse.', 'placeholder' => 'Musterstadt'],
            ['label' => 'Land', 'slug' => 'country', 'field_type' => 'text', 'is_required' => false, 'help_text' => 'Optional, Standard ist Deutschland.', 'placeholder' => 'Deutschland'],
            ['label' => 'Teilnehmerzahl', 'slug' => 'participant_count', 'field_type' => 'number', 'is_required' => true, 'help_text' => 'Wie viele Personen sollen angemeldet werden?', 'placeholder' => '1'],
            ['label' => 'Hinweis zur Gruppe', 'slug' => 'participant_notes', 'field_type' => 'textarea', 'is_required' => false, 'help_text' => 'Optional: Hinweise zur Gruppe oder besondere Wünsche.', 'placeholder' => 'z. B. Kindersitz erforderlich'],
        ];

        foreach ($requiredFields as $index => $field) {
            $form->fields()->updateOrCreate(
                ['slug' => $field['slug']],
                $field + ['sort_order' => $index + 1]
            );
        }
    }

    private function participantViewData(Event $event): array
    {
        if (!$event->booking_enabled) {
            return [
                'eventBookings' => collect(),
                'bookingSubmissionCount' => 0,
                'participantCount' => 0,
                'bookingRevenue' => 0,
            ];
        }

        $bookings = $event->bookings()
            ->with(['participants', 'submission'])
            ->paginate(20, ['*'], 'anmeldungen');

        $bookingTotals = EventBooking::query()
            ->where('event_id', $event->id)
            ->where('tenant_id', $event->tenant_id)
            ->selectRaw('COUNT(*) as booking_count, COALESCE(SUM(participant_count), 0) as participant_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        return [
            'eventBookings' => $bookings,
            'bookingSubmissionCount' => (int) ($bookingTotals->booking_count ?? 0),
            'participantCount' => (int) ($bookingTotals->participant_count ?? 0),
            'bookingRevenue' => (float) ($bookingTotals->revenue ?? 0),
        ];
    }

    private function publicListData(string $tenantSlug, Request $request): array
    {
        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->firstOrFail();

        $categories = EventCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        $selectedCategorySlug = $request->string('category')->toString();
        $selectedCategory = $selectedCategorySlug
            ? $categories->firstWhere('slug', $selectedCategorySlug)
            : null;

        $events = Event::withoutGlobalScopes()
            ->with('category')
            ->where('tenant_id', $tenant->id)
            ->where('is_public', true)
            ->where('end', '>=', now()->startOfDay())
            ->when($selectedCategory, fn ($query) => $query->where('category_id', $selectedCategory->id))
            ->orderBy('start')
            ->paginate(12)
            ->withQueryString();

        return [
            'tenant' => $tenant,
            'categories' => $categories,
            'selectedCategorySlug' => $selectedCategorySlug,
            'events' => $events,
            'groupedEvents' => $events->getCollection()->groupBy(fn (Event $event) => $event->month_group_label),
            'embedUrl' => route('events.public.embed', ['tenantSlug' => $tenant->slug]),
            'publicListUrl' => route('events.public.index', ['tenantSlug' => $tenant->slug]),
        ];
    }

    private function shiftViewData(Event $event): array
    {
        $event->loadMissing(['shifts.assignments.member']);

        $shifts = $event->shifts;
        $totalRequired = $shifts->sum('required_people');
        $totalConfirmed = $shifts->sum(fn ($shift) => $shift->confirmed_assignments_count);
        $openSlots = $shifts->sum(fn ($shift) => $shift->open_slots);

        $assignableMembers = Member::query()
            ->where('tenant_id', $event->tenant_id)
            ->whereNull('archived_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return [
            'eventShifts' => $shifts,
            'assignableMembers' => $assignableMembers,
            'scheduleStats' => [
                'shift_count' => $shifts->count(),
                'total_required' => $totalRequired,
                'total_confirmed' => $totalConfirmed,
                'open_slots' => $openSlots,
                'coverage_status' => $openSlots > 0 ? 'understaffed' : ($totalConfirmed > $totalRequired ? 'overstaffed' : 'full'),
            ],
        ];
    }

    private function eventBookingDescription(Event $event): string
    {
        $base = 'Melde dich hier verbindlich zur Veranstaltung an.';

        if (!$event->booking_enabled) {
            return $base;
        }

        if (!$event->is_paid) {
            return $base . ' Die Teilnahme ist kostenfrei.';
        }

        return $base
            . ' Preis: ' . number_format((float) $event->price_per_person, 2, ',', '.') . ' ' . strtoupper($event->currency ?: 'EUR')
            . ' pro Person. Nach der Buchung erhaeltst du automatisch eine Rechnung per E-Mail.';
    }

    private function attachConflictState($events)
    {
        return $events->map(function (Event $event) {
            $conflicts = $this->findConflictingEvents($event);

            $event->setAttribute('conflicting_events', $conflicts);
            $event->setAttribute('conflict_count', $conflicts->count());

            return $event;
        });
    }

    private function findConflictingEvents(Event $event)
    {
        return Event::query()
            ->with(['category', 'responsibleUser'])
            ->where('tenant_id', $event->tenant_id)
            ->whereKeyNot($event->id)
            ->where('start', '<', $event->end)
            ->where('end', '>', $event->start)
            ->orderBy('start')
            ->get();
    }

    private function logEventChange(Event $event, string $action, ?array $beforeState, ?array $afterState, ?string $summary): void
    {
        EventChangeLog::create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'summary' => $summary,
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ]);
    }

    private function buildUpdateSummary(?array $beforeState, ?array $afterState): string
    {
        if (!$beforeState || !$afterState) {
            return 'Termin aktualisiert';
        }

        $labels = [
            'title' => 'Titel',
            'description' => 'Beschreibung',
            'location' => 'Ort',
            'start' => 'Beginn',
            'end' => 'Ende',
            'category_id' => 'Kategorie',
            'responsible_user_id' => 'Verantwortlich',
            'is_public' => 'Sichtbarkeit',
        ];

        $changed = collect($labels)
            ->filter(fn ($label, $field) => (string) data_get($beforeState, $field) !== (string) data_get($afterState, $field))
            ->values()
            ->all();

        if ($changed === []) {
            return 'Termin aktualisiert';
        }

        return 'Geaendert: ' . implode(', ', $changed);
    }
}
