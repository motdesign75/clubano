<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventBooking;
use App\Models\EventBookingParticipant;
use App\Models\EventChangeLog;
use App\Models\EventCategory;
use App\Models\EventInvitation;
use App\Models\EventShift;
use App\Models\EventShiftAssignment;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

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

    public function poster(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'category_id' => ['nullable', Rule::exists('event_categories', 'id')->where('tenant_id', $tenantId)],
            'visibility' => 'nullable|in:all,public,internal',
        ]);

        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->toDateString())->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'] ?? now()->addMonths(3)->toDateString())->endOfDay();
        $visibility = $filters['visibility'] ?? 'all';

        $events = Event::query()
            ->with(['category', 'responsibleUser'])
            ->where('tenant_id', $tenantId)
            ->where('end', '>=', $dateFrom)
            ->where('start', '<=', $dateTo)
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($visibility === 'public', fn ($query) => $query->where('is_public', true))
            ->when($visibility === 'internal', fn ($query) => $query->where('is_public', false))
            ->orderBy('start')
            ->get();

        return view('events.poster', [
            'events' => $events,
            'categories' => EventCategory::query()->orderBy('name')->get(),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'category_id' => $filters['category_id'] ?? '',
                'visibility' => $visibility,
            ],
        ]);
    }

    public function posterPrint(Request $request)
    {
        $data = $this->posterPrintData($request);

        return view('events.poster-print', [
            ...$data,
        ]);
    }

    public function posterPdf(Request $request)
    {
        $data = $this->posterPrintData($request);

        $pdf = Pdf::loadView('pdf.event-poster', $data)->setPaper('a4', 'portrait');

        return $pdf->stream('terminaushang-' . Str::slug($data['headline'] ?: 'termine') . '.pdf');
    }

    private function posterPrintData(Request $request): array
    {
        $tenantId = auth()->user()->tenant_id;
        $validated = $request->validate([
            'event_ids' => 'required|array|min:1',
            'event_ids.*' => ['integer', Rule::exists('events', 'id')->where('tenant_id', $tenantId)],
            'headline' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        $eventIds = collect($validated['event_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $events = Event::query()
            ->with(['category', 'responsibleUser', 'tenant'])
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $eventIds)
            ->orderBy('start')
            ->get();

        if ($events->isEmpty()) {
            back()
                ->withErrors(['event_ids' => 'Bitte wähle mindestens eine Veranstaltung für den Aushang aus.'])
                ->withInput()
                ->throwResponse();
        }

        return [
            'events' => $events,
            'tenant' => $events->first()->tenant,
            'headline' => $validated['headline'] ?? 'Aktuelle Termine',
            'note' => $validated['note'] ?? null,
        ];
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
                'attendance_enabled' => false,
                'response_required' => false,
                'counts_toward_required_hours' => false,
                'reminders_enabled' => false,
            ]),
            'categories' => EventCategory::query()->with('defaultTargetTag')->orderBy('name')->get(),
            'targetTags' => Tag::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
            'users' => User::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
        ]);
    }

    /**
     * Event speichern
     */
    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate($this->eventValidationRules($tenantId) + [
            'recurrence_enabled' => 'nullable|boolean',
            'recurrence_frequency' => 'required_if:recurrence_enabled,1|nullable|in:weekly,biweekly,monthly,monthly_same_date,monthly_nth_weekday',
            'recurrence_until' => 'required_if:recurrence_enabled,1|nullable|date|after_or_equal:start',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('events', 'public');
        }

        $baseData = $this->eventDataFromRequest($validated, $request);
        $baseData['tenant_id'] = Auth::user()->tenant_id;
        $baseData['created_by'] = Auth::id();
        $baseData['updated_by'] = Auth::id();

        $events = $this->createEventsFromSeries($baseData, $request->boolean('recurrence_enabled'), $validated['recurrence_frequency'] ?? null, $validated['recurrence_until'] ?? null);
        $event = $events->first();

        $events->each(function (Event $seriesEvent) {
            $this->syncBookingForm($seriesEvent);
            $this->logEventChange($seriesEvent, 'created', null, $seriesEvent->fresh()->toArray(), 'Termin angelegt');
        });

        $message = $events->count() > 1
            ? $events->count() . ' Serientermine wurden gespeichert.'
            : 'Event wurde gespeichert.';

        return redirect()->route('events.edit', $event)->with('success', $message);
    }

    private function eventValidationRules(int $tenantId): array
    {
        return [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'location'    => 'nullable|string|max:255',
            'start'       => 'required|date',
            'end'         => 'required|date|after_or_equal:start',
            'category_id' => ['nullable', Rule::exists('event_categories', 'id')->where('tenant_id', $tenantId)],
            'responsible_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'target_tag_id' => ['nullable', Rule::exists('tags', 'id')->where('tenant_id', $tenantId)],
            'is_public'   => 'required|boolean',
            'booking_enabled' => 'nullable|boolean',
            'attendance_enabled' => 'nullable|boolean',
            'response_required' => 'nullable|boolean',
            'counts_toward_required_hours' => 'nullable|boolean',
            'reminders_enabled' => 'nullable|boolean',
            'price_per_person' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants_per_booking' => 'nullable|integer|min:1|max:50',
            'image'       => 'nullable|image|max:5120',
        ];
    }

    private function eventDataFromRequest(array $validated, Request $request, ?Event $event = null): array
    {
        return [
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location'    => $validated['location'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'] ?? null,
            'target_tag_id' => $validated['target_tag_id'] ?? null,
            'start'       => $validated['start'],
            'end'         => $validated['end'],
            'is_public'   => (bool) $validated['is_public'],
            'booking_enabled' => $request->boolean('booking_enabled'),
            'attendance_enabled' => $request->boolean('attendance_enabled'),
            'response_required' => $request->boolean('response_required'),
            'counts_toward_required_hours' => $request->boolean('counts_toward_required_hours'),
            'reminders_enabled' => $request->boolean('reminders_enabled'),
            'price_per_person' => $request->boolean('booking_enabled') ? ($validated['price_per_person'] ?? 0) : 0,
            'currency' => strtoupper($validated['currency'] ?? ($event?->currency ?: 'EUR')),
            'max_participants_per_booking' => $validated['max_participants_per_booking'] ?? ($event?->max_participants_per_booking ?: 1),
            'image_path'  => array_key_exists('image_path', $validated) ? $validated['image_path'] : $event?->image_path,
        ];
    }

    private function createEventsFromSeries(array $baseData, bool $recurrenceEnabled, ?string $frequency, ?string $until)
    {
        if (! $recurrenceEnabled) {
            return collect([Event::create($baseData)]);
        }

        $start = Carbon::parse($baseData['start']);
        $end = Carbon::parse($baseData['end']);
        $untilDate = Carbon::parse($until)->endOfDay();
        $groupId = (string) Str::uuid();
        $durationInSeconds = $start->diffInSeconds($end);
        $events = collect();

        foreach ($this->recurringEventStarts($start, $untilDate, $frequency) as $occurrenceStart) {
            $occurrenceEnd = $occurrenceStart->copy()->addSeconds($durationInSeconds);

            $events->push(Event::create(array_merge($baseData, [
                'start' => $occurrenceStart->toDateTimeString(),
                'end' => $occurrenceEnd->toDateTimeString(),
                'recurrence_group_id' => $groupId,
                'recurrence_frequency' => $frequency,
                'recurrence_interval' => $frequency === 'biweekly' ? 2 : 1,
                'recurrence_until' => $untilDate->toDateString(),
            ])));
        }

        return $events;
    }

    private function recurringEventStarts(Carbon $start, Carbon $untilDate, ?string $frequency)
    {
        $starts = collect();
        $cursor = $start->copy();

        while ($cursor->lte($untilDate) && $starts->count() < 80) {
            $starts->push($cursor->copy());

            $cursor = match ($frequency) {
                'monthly', 'monthly_same_date' => $cursor->copy()->addMonthNoOverflow(),
                'monthly_nth_weekday' => $this->nextMonthlyNthWeekday($start, $cursor),
                'biweekly' => $cursor->copy()->addWeeks(2),
                default => $cursor->copy()->addWeek(),
            };
        }

        return $starts;
    }

    private function nextMonthlyNthWeekday(Carbon $seriesStart, Carbon $currentOccurrence): Carbon
    {
        $monthCursor = $currentOccurrence->copy()->firstOfMonth()->addMonth();
        $weekday = $seriesStart->dayOfWeek;
        $time = [
            $seriesStart->hour,
            $seriesStart->minute,
            $seriesStart->second,
        ];
        $isLastWeekdayOfMonth = $seriesStart->copy()->addWeek()->month !== $seriesStart->month;
        $nthWeekday = (int) ceil($seriesStart->day / 7);

        if ($isLastWeekdayOfMonth) {
            $candidate = $monthCursor->copy()->endOfMonth();

            while ($candidate->dayOfWeek !== $weekday) {
                $candidate->subDay();
            }

            return $candidate->setTime(...$time);
        }

        $candidate = $monthCursor->copy()->startOfMonth();

        while ($candidate->dayOfWeek !== $weekday) {
            $candidate->addDay();
        }

        $candidate->addWeeks($nthWeekday - 1)->setTime(...$time);

        if ($candidate->month === $monthCursor->month) {
            return $candidate;
        }

        return $this->nextMonthlyNthWeekday($seriesStart, $monthCursor);
    }

    /**
     * Event-Formular zum Bearbeiten
     */
    public function edit(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['activeBookingForm.fields', 'shifts.assignments.member', 'category.defaultTargetTag', 'targetTag', 'tenant', 'responsibleUser', 'creator', 'updater', 'changeLogs.user']);
        $event->setAttribute('conflicting_events', $this->findConflictingEvents($event));
        $event->setAttribute('conflict_count', $event->conflicting_events->count());

        return view('events.edit', [
            'event' => $event,
            'categories' => EventCategory::query()->with('defaultTargetTag')->orderBy('name')->get(),
            'targetTags' => Tag::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
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

        $validated = $request->validate($this->eventValidationRules($tenantId));

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

        $event->update($this->eventDataFromRequest($validated, $request, $event) + [
            'updated_by' => Auth::id(),
        ]);

        $this->syncBookingForm($event);
        $this->logEventChange($event, 'updated', $before, $event->fresh()->toArray(), $this->buildUpdateSummary($before, $event->fresh()->toArray()));

        return redirect()->route('events.edit', $event)->with('success', 'Event aktualisiert.');
    }

    public function show(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant', 'activeBookingForm.fields', 'shifts.assignments.member', 'category', 'targetTag', 'responsibleUser', 'creator', 'updater', 'changeLogs.user', 'attendances.member', 'invitations.member.tags']);
        $event->setAttribute('conflicting_events', $this->findConflictingEvents($event));
        $event->setAttribute('conflict_count', $event->conflicting_events->count());

        return view('events.show', [
            'event' => $event,
            'isPublicPreview' => false,
            ...$this->invitationViewData($event),
            ...$this->attendanceViewData($event),
            ...$this->participantViewData($event),
            ...$this->shiftViewData($event),
        ]);
    }

    public function syncInvitations(Event $event)
    {
        $this->authorizeEvent($event);

        $members = $this->ensureInvitationsForEvent($event);

        return redirect()->route('events.show', $event)->with('success', $members->count() . ' Mitglieder sind in der Einladungsliste.');
    }

    public function sendInvitationMails(Event $event)
    {
        $this->authorizeEvent($event);

        $this->ensureInvitationsForEvent($event);
        $event->load(['tenant', 'invitations.member']);

        $tenant = $event->tenant;
        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;
        $sentCount = 0;
        $skippedCount = 0;

        foreach ($event->invitations as $invitation) {
            $member = $invitation->member;

            if (! $member || blank($member->email) || blank($invitation->response_token)) {
                $skippedCount++;
                continue;
            }

            $responseUrl = route('events.invitations.public.show', $invitation->response_token);
            Mail::to($member->email, $member->full_name ?: null)->send(
                new EventInvitationMail($invitation, $responseUrl, $fromAddress, $fromName, $replyToAddress)
            );

            $sentCount++;
        }

        $message = $sentCount . ' Einladung' . ($sentCount === 1 ? '' : 'en') . ' per Mail gesendet.';

        if ($skippedCount > 0) {
            $message .= ' ' . $skippedCount . ' ohne E-Mail-Adresse übersprungen.';
        }

        return redirect()->route('events.show', $event)->with('success', $message);
    }

    public function updateInvitations(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'invitations' => 'nullable|array',
            'invitations.*.id' => ['required', Rule::exists('event_invitations', 'id')->where('tenant_id', $event->tenant_id)],
            'invitations.*.status' => ['required', Rule::in(EventInvitation::STATUSES)],
            'invitations.*.note' => 'nullable|string|max:500',
        ]);

        foreach ($validated['invitations'] ?? [] as $invitationData) {
            $invitation = EventInvitation::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->findOrFail($invitationData['id']);

            $statusChanged = $invitation->status !== $invitationData['status'];

            $invitation->update([
                'status' => $invitationData['status'],
                'note' => $invitationData['note'] ?? null,
                'responded_at' => $statusChanged ? now() : $invitation->responded_at,
                'recorded_by' => auth()->id(),
            ]);
        }

        return redirect()->route('events.show', $event)->with('success', 'Rückmeldungen wurden gespeichert.');
    }

    public function invitationResponse(string $token)
    {
        $invitation = EventInvitation::query()
            ->with(['event.tenant', 'event.category', 'member'])
            ->where('response_token', $token)
            ->firstOrFail();

        return view('events.invitation-response', [
            'invitation' => $invitation,
            'event' => $invitation->event,
            'member' => $invitation->member,
        ]);
    }

    public function storeInvitationResponse(Request $request, string $token)
    {
        $invitation = EventInvitation::query()
            ->with(['event.tenant', 'member'])
            ->where('response_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EventInvitation::STATUS_ACCEPTED,
                EventInvitation::STATUS_DECLINED,
                EventInvitation::STATUS_MAYBE,
            ])],
            'note' => 'nullable|string|max:500',
        ]);

        $invitation->update([
            'status' => $validated['status'],
            'note' => $validated['note'] ?? null,
            'responded_at' => now(),
            'recorded_by' => null,
        ]);

        return redirect()->route('events.invitations.public.show', $invitation->response_token)
            ->with('success', 'Danke, deine Rückmeldung wurde gespeichert.');
    }

    public function updateAttendance(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'attendances' => 'nullable|array',
            'attendances.*.member_id' => ['required', Rule::exists('members', 'id')->where('tenant_id', $tenantId)],
            'attendances.*.attended' => 'nullable|boolean',
            'attendances.*.hours' => 'nullable|numeric|min:0|max:999.99',
            'attendances.*.counts_toward_required_hours' => 'nullable|boolean',
            'attendances.*.note' => 'nullable|string|max:500',
        ]);

        foreach ($validated['attendances'] ?? [] as $attendanceData) {
            $attended = (bool) ($attendanceData['attended'] ?? false);
            $hours = $attended ? round((float) ($attendanceData['hours'] ?? 0), 2) : 0;

            EventAttendance::query()->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'member_id' => $attendanceData['member_id'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'attended' => $attended,
                    'hours' => $hours,
                    'counts_toward_required_hours' => $attended && (bool) ($attendanceData['counts_toward_required_hours'] ?? false),
                    'note' => $attendanceData['note'] ?? null,
                    'recorded_by' => auth()->id(),
                ]
            );
        }

        return redirect()->route('events.show', $event)->with('success', 'Anwesenheit wurde gespeichert.');
    }

    public function attendanceReport(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $today = now();

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'member_id' => ['nullable', Rule::exists('members', 'id')->where('tenant_id', $tenantId)],
            'only_open' => 'nullable|boolean',
        ]);

        $dateFrom = filled($validated['date_from'] ?? null)
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : $today->copy()->startOfYear();
        $dateTo = filled($validated['date_to'] ?? null)
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : $today->copy()->endOfYear();
        $memberId = $validated['member_id'] ?? null;
        $onlyOpen = $request->boolean('only_open');

        $members = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $attendances = EventAttendance::query()
            ->with(['event.category', 'member'])
            ->where('tenant_id', $tenantId)
            ->where('attended', true)
            ->whereHas('event', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('start', [$dateFrom, $dateTo]);
            })
            ->when($memberId, fn ($query) => $query->where('member_id', $memberId))
            ->get();

        $attendancesByMember = $attendances->groupBy('member_id');
        $memberSummaries = $members
            ->when($memberId, fn ($collection) => $collection->where('id', (int) $memberId))
            ->map(function (Member $member) use ($attendancesByMember) {
                $memberAttendances = $attendancesByMember->get($member->id, collect());
                $countedHours = round((float) $memberAttendances->where('counts_toward_required_hours', true)->sum('hours'), 2);
                $totalHours = round((float) $memberAttendances->sum('hours'), 2);
                $requiredHours = round((float) $member->required_service_hours, 2);
                $remainingHours = max(0, round($requiredHours - $countedHours, 2));

                return [
                    'member' => $member,
                    'attendances_count' => $memberAttendances->count(),
                    'total_hours' => $totalHours,
                    'counted_hours' => $countedHours,
                    'required_hours' => $requiredHours,
                    'remaining_hours' => $remainingHours,
                    'completion_percent' => $requiredHours > 0 ? min(100, round(($countedHours / $requiredHours) * 100)) : 100,
                ];
            })
            ->filter(fn ($summary) => ! $onlyOpen || $summary['remaining_hours'] > 0)
            ->sortByDesc('remaining_hours')
            ->values();

        $eventSummaries = $attendances
            ->groupBy('event_id')
            ->map(function ($eventAttendances) {
                $event = $eventAttendances->first()->event;

                return [
                    'event' => $event,
                    'present' => $eventAttendances->count(),
                    'total_hours' => round((float) $eventAttendances->sum('hours'), 2),
                    'counted_hours' => round((float) $eventAttendances->where('counts_toward_required_hours', true)->sum('hours'), 2),
                ];
            })
            ->sortBy(fn ($summary) => $summary['event']?->start)
            ->values();

        return view('events.attendance-report', [
            'members' => $members,
            'memberSummaries' => $memberSummaries,
            'eventSummaries' => $eventSummaries,
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'member_id' => $memberId,
                'only_open' => $onlyOpen,
            ],
            'reportStats' => [
                'present_records' => $attendances->count(),
                'members_with_attendance' => $attendances->pluck('member_id')->unique()->count(),
                'total_hours' => round((float) $attendances->sum('hours'), 2),
                'counted_hours' => round((float) $attendances->where('counts_toward_required_hours', true)->sum('hours'), 2),
                'open_members' => $memberSummaries->where('remaining_hours', '>', 0)->count(),
            ],
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

        $bookings = $event->bookings()->with('participants')->get();
        $fieldLabels = $event->activeBookingForm?->fields?->pluck('label', 'slug') ?? collect();

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
        $columns[] = 'teilnehmer_details';

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
                    ->map(fn ($participant) => $participant->display_name)
                    ->implode(', ');

                $row[] = $booking->participants
                    ->map(fn ($participant) => implode(' | ', [
                        $participant->display_name,
                        $participant->type_label,
                        number_format((float) $participant->price_amount, 2, '.', ''),
                        $participant->payment_status,
                        $participant->source,
                    ]))
                    ->implode(', ');

                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function participantsPrint(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        return view('events.participants-print', $this->participantsDocumentData($event, $request));
    }

    public function participantsPdf(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $pdf = Pdf::loadView('pdf.event-participants', $this->participantsDocumentData($event, $request))
            ->setPaper('a4', 'portrait');

        $filename = 'teilnehmerliste-' . Str::slug($event->title ?: 'termin') . '.pdf';

        return $pdf->stream($filename);
    }

    private function participantsDocumentData(Event $event, ?Request $request = null): array
    {
        $displayMode = $request?->query('display') === 'organization' ? 'organization' : 'person';

        $event->load(['tenant', 'bookings.participants.member', 'bookings.participants.contact']);
        $participants = $event->bookings
            ->flatMap(fn (EventBooking $booking) => $booking->participants->map(fn ($participant) => [
                'booking' => $booking,
                'participant' => $participant,
                'display_name' => $this->participantDocumentDisplayName($participant, $displayMode),
                'display_subline' => $this->participantDocumentSubline($participant, $displayMode),
            ]))
            ->sortBy(fn (array $row) => mb_strtolower($row['display_name']))
            ->values();

        return [
            'event' => $event,
            'tenant' => $event->tenant,
            'participants' => $participants,
            'displayMode' => $displayMode,
            'stats' => [
                'count' => $participants->count(),
                'open' => $participants->filter(fn (array $row) => $row['participant']->payment_status === 'open')->count(),
                'paid' => $participants->filter(fn (array $row) => $row['participant']->payment_status === 'paid')->count(),
                'free' => $participants->filter(fn (array $row) => $row['participant']->payment_status === 'not_required')->count(),
                'total' => $participants->sum(fn (array $row) => (float) $row['participant']->price_amount),
            ],
        ];
    }

    private function participantDocumentDisplayName(EventBookingParticipant $participant, string $displayMode): string
    {
        $personName = trim($participant->full_name);
        $organization = trim((string) ($participant->organization_name
            ?: $participant->member?->organization
            ?: $participant->contact?->organization
            ?: $participant->contact?->company));

        if ($displayMode === 'organization') {
            return $organization ?: ($personName ?: $participant->display_name ?: 'Ohne Namen');
        }

        return $personName ?: ($organization ?: $participant->display_name ?: 'Ohne Namen');
    }

    private function participantDocumentSubline(EventBookingParticipant $participant, string $displayMode): string
    {
        $personName = trim($participant->full_name);
        $organization = trim((string) ($participant->organization_name
            ?: $participant->member?->organization
            ?: $participant->contact?->organization
            ?: $participant->contact?->company));

        if ($displayMode === 'organization' && $organization && $personName) {
            return $personName;
        }

        if ($displayMode === 'person' && $organization) {
            return $organization;
        }

        return $participant->note ?: ($participant->source ?: 'manual');
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

    public function updateParticipant(Request $request, Event $event, EventBooking $booking, EventBookingParticipant $participant)
    {
        $this->authorizeEvent($event);
        $this->authorizeBooking($event, $booking);

        abort_unless((int) $participant->event_booking_id === (int) $booking->id, 404);

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'payment_required' => ['nullable', 'boolean'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'payment_status' => ['required', Rule::in(['not_required', 'open', 'paid', 'cancelled'])],
            'payment_reason' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::in(['manual', 'phone', 'email', 'abendkasse', 'imported', 'online'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $paymentRequired = $request->boolean('payment_required');
        $priceAmount = $paymentRequired ? round((float) ($validated['price_amount'] ?? 0), 2) : 0;
        $paymentStatus = $paymentRequired ? $validated['payment_status'] : 'not_required';

        if ($priceAmount <= 0 && $paymentStatus === 'open') {
            $paymentStatus = 'not_required';
        }

        $participant->update([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'organization_name' => $validated['organization_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'payment_required' => $paymentRequired,
            'price_amount' => $priceAmount,
            'payment_status' => $paymentStatus,
            'payment_reason' => $validated['payment_reason'] ?? null,
            'source' => $validated['source'],
            'note' => $validated['note'] ?? null,
        ]);

        $booking->recalculateTotalsFromParticipants();

        if ($booking->participants()->count() === 1) {
            $participant->refresh();
            $booking->forceFill([
                'booker_name' => $participant->display_name ?: $participant->full_name ?: $booking->booker_name,
                'booker_email' => $participant->email,
                'booker_phone' => $participant->phone,
            ])->save();
        }

        return redirect()
            ->route('events.edit', $event)
            ->with('success', 'Teilnehmer wurde aktualisiert.');
    }

    public function markParticipantsFree(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer'],
            'payment_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $participants = EventBookingParticipant::query()
            ->whereIn('id', $validated['participant_ids'])
            ->whereHas('booking', fn ($query) => $query->where('event_id', $event->id)->where('tenant_id', $event->tenant_id))
            ->with('booking')
            ->get();

        $participants->each(function (EventBookingParticipant $participant) use ($validated) {
            $participant->update([
                'payment_required' => false,
                'price_amount' => 0,
                'payment_status' => 'not_required',
                'payment_reason' => $validated['payment_reason'] ?? $participant->payment_reason,
            ]);
        });

        $participants
            ->pluck('booking')
            ->filter()
            ->unique('id')
            ->each(fn (EventBooking $booking) => $booking->recalculateTotalsFromParticipants());

        return redirect()
            ->route('events.edit', $event)
            ->with('success', $participants->count() . ' Teilnehmer wurden kostenfrei gesetzt.');
    }

    public function storeManualParticipant(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'participant_type' => ['required', Rule::in(['member', 'contact', 'guest'])],
            'member_id' => ['nullable', Rule::exists('members', 'id')->where('tenant_id', $tenantId)],
            'member_ids' => ['required_if:participant_type,member', 'nullable', 'array', 'min:1'],
            'member_ids.*' => ['integer', Rule::exists('members', 'id')->where('tenant_id', $tenantId)],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)],
            'contact_ids' => ['required_if:participant_type,contact', 'nullable', 'array', 'min:1'],
            'contact_ids.*' => ['integer', Rule::exists('contacts', 'id')->where('tenant_id', $tenantId)],
            'guest_mode' => ['nullable', Rule::in(['person', 'organization'])],
            'organization_name' => [
                Rule::requiredIf(fn () => $request->input('participant_type') === 'guest' && $request->input('guest_mode', 'person') === 'organization'),
                'nullable',
                'string',
                'max:255',
            ],
            'first_name' => [
                Rule::requiredIf(fn () => $request->input('participant_type') === 'guest' && $request->input('guest_mode', 'person') !== 'organization'),
                'nullable',
                'string',
                'max:255',
            ],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'payment_required' => ['nullable', 'boolean'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'payment_status' => ['required', Rule::in(['not_required', 'open', 'paid', 'cancelled'])],
            'payment_reason' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::in(['manual', 'phone', 'email', 'abendkasse', 'imported'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $participants = $this->manualParticipantPayloads($validated);
        $paymentRequired = $request->boolean('payment_required');
        $priceAmount = $paymentRequired ? round((float) ($validated['price_amount'] ?? $event->price_per_person ?? 0), 2) : 0;
        $paymentStatus = $paymentRequired ? $validated['payment_status'] : 'not_required';

        if ($priceAmount <= 0 && $paymentStatus === 'open') {
            $paymentStatus = 'not_required';
        }

        $bookingName = $participants->count() === 1
            ? $participants->first()['booker_name']
            : $participants->count() . ' manuell nachgetragene Teilnehmer';
        $firstParticipant = $participants->first()['data'];

        $booking = EventBooking::create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'booking_reference' => $this->generateBookingReference($event),
            'booker_name' => $bookingName,
            'booker_email' => $participants->count() === 1 ? ($firstParticipant['email'] ?? null) : null,
            'booker_phone' => $participants->count() === 1 ? ($firstParticipant['phone'] ?? null) : null,
            'participant_count' => $participants->count(),
            'price_per_person' => $priceAmount,
            'total_amount' => $priceAmount * $participants->count(),
            'currency' => strtoupper($event->currency ?: 'EUR'),
            'payment_status' => $paymentStatus,
            'booking_status' => 'confirmed',
            'notes' => trim('Manuell nachgetragen' . (filled($validated['note'] ?? null) ? ': ' . $validated['note'] : '')),
        ]);

        $participants->each(function (array $participant, int $index) use ($booking, $validated, $paymentRequired, $priceAmount, $paymentStatus) {
            $booking->participants()->create(array_merge($participant['data'], [
                'position' => $index + 1,
                'participant_type' => $validated['participant_type'],
                'payment_required' => $paymentRequired,
                'price_amount' => $priceAmount,
                'payment_status' => $paymentStatus,
                'payment_reason' => $validated['payment_reason'] ?? null,
                'source' => $validated['source'],
                'note' => $validated['note'] ?? null,
            ]));
        });

        $booking->recalculateTotalsFromParticipants();

        return redirect()
            ->route('events.edit', $event)
            ->with('success', $participants->count() . ' Teilnehmer wurden nachgetragen.');
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
        $bookings = $event->bookings()
            ->with(['participants.member', 'participants.contact', 'submission'])
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
            'manualParticipantMembers' => Member::query()
                ->where('tenant_id', $event->tenant_id)
                ->whereNull('archived_at')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'organization', 'email', 'mobile', 'landline']),
            'manualParticipantContacts' => Contact::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('is_active', true)
                ->orderBy('organization')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'organization', 'company', 'first_name', 'last_name', 'email', 'mobile', 'phone', 'phone_mobile', 'phone_landline']),
        ];
    }

    private function manualParticipantPayloads(array $validated)
    {
        if ($validated['participant_type'] === 'member') {
            $memberIds = collect($validated['member_ids'] ?? [$validated['member_id'] ?? null])->filter()->unique()->values();

            return Member::forCurrentTenant()
                ->whereIn('id', $memberIds)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(function (Member $member) use ($validated) {
                    $fullName = trim($member->full_name);

                    return [
                        'booker_name' => $fullName ?: 'Mitglied #' . $member->id,
                        'data' => [
                            'member_id' => $member->id,
                            'contact_id' => null,
                            'first_name' => $member->first_name ?: ($fullName ?: 'Mitglied'),
                            'last_name' => $member->last_name ?: '',
                            'email' => $validated['email'] ?? $member->email,
                            'phone' => $validated['phone'] ?? ($member->mobile ?: $member->landline),
                        ],
                    ];
                });
        }

        if ($validated['participant_type'] === 'contact') {
            $contactIds = collect($validated['contact_ids'] ?? [$validated['contact_id'] ?? null])->filter()->unique()->values();

            return Contact::forCurrentTenant()
                ->whereIn('id', $contactIds)
                ->orderBy('organization')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(function (Contact $contact) use ($validated) {
                    $displayName = $contact->display_name;

                    return [
                        'booker_name' => $displayName,
                        'data' => [
                            'member_id' => null,
                            'contact_id' => $contact->id,
                            'first_name' => $contact->first_name ?: $displayName,
                            'last_name' => $contact->last_name ?: '',
                            'email' => $validated['email'] ?? $contact->primary_email,
                            'phone' => $validated['phone'] ?? $contact->primary_phone,
                        ],
                    ];
                });
        }

        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));
        $organizationName = trim((string) ($validated['organization_name'] ?? ''));
        $bookerName = $organizationName ?: (trim($firstName . ' ' . $lastName) ?: 'Gast');

        return collect([[
            'booker_name' => $bookerName,
            'data' => [
                'member_id' => null,
                'contact_id' => null,
                'first_name' => $organizationName ? '' : ($firstName ?: $bookerName),
                'last_name' => $lastName,
                'organization_name' => $organizationName ?: null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ],
        ]]);
    }

    private function generateBookingReference(Event $event): string
    {
        do {
            $reference = sprintf('EVT-%d-%s', $event->id, Str::upper(Str::random(6)));
        } while (EventBooking::query()->where('booking_reference', $reference)->exists());

        return $reference;
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

    private function attendanceViewData(Event $event): array
    {
        $event->loadMissing(['attendances.member']);

        $members = $this->invitableMembersForEvent($event);

        $attendancesByMember = $event->attendances->keyBy('member_id');

        return [
            'attendanceMembers' => $members,
            'attendancesByMember' => $attendancesByMember,
            'attendanceStats' => [
                'present' => $event->attendances->where('attended', true)->count(),
                'total_hours' => round((float) $event->attendances->where('attended', true)->sum('hours'), 2),
                'counted_hours' => round((float) $event->attendances
                    ->where('attended', true)
                    ->where('counts_toward_required_hours', true)
                    ->sum('hours'), 2),
            ],
        ];
    }

    private function invitationViewData(Event $event): array
    {
        $event->loadMissing(['invitations.member']);

        $event->invitations
            ->whereNull('response_token')
            ->each(fn (EventInvitation $invitation) => $invitation->update(['response_token' => EventInvitation::newResponseToken()]));

        $event->load('invitations.member');

        $invitations = $event->invitations
            ->filter(fn (EventInvitation $invitation) => $invitation->member !== null)
            ->sortBy(fn (EventInvitation $invitation) => $invitation->member->last_name . ' ' . $invitation->member->first_name)
            ->values();

        $statusCounts = $invitations->countBy('status');
        $targetMemberCount = $this->invitableMembersForEvent($event)->count();

        return [
            'eventInvitations' => $invitations,
            'invitationStats' => [
                'target_members' => $targetMemberCount,
                'total' => $invitations->count(),
                'accepted' => (int) ($statusCounts[EventInvitation::STATUS_ACCEPTED] ?? 0),
                'declined' => (int) ($statusCounts[EventInvitation::STATUS_DECLINED] ?? 0),
                'maybe' => (int) ($statusCounts[EventInvitation::STATUS_MAYBE] ?? 0),
                'open' => $invitations->whereIn('status', [EventInvitation::STATUS_INVITED, EventInvitation::STATUS_NO_RESPONSE])->count(),
            ],
            'invitationStatuses' => [
                EventInvitation::STATUS_INVITED => 'Eingeladen',
                EventInvitation::STATUS_ACCEPTED => 'Zusage',
                EventInvitation::STATUS_DECLINED => 'Absage',
                EventInvitation::STATUS_MAYBE => 'Vielleicht',
                EventInvitation::STATUS_NO_RESPONSE => 'Keine Rückmeldung',
                EventInvitation::STATUS_EXCUSED => 'Entschuldigt',
            ],
        ];
    }

    private function invitableMembersForEvent(Event $event)
    {
        return Member::query()
            ->where('tenant_id', $event->tenant_id)
            ->whereNull('archived_at')
            ->when($event->target_tag_id, fn ($query) => $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('tags.id', $event->target_tag_id)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function ensureInvitationsForEvent(Event $event)
    {
        $members = $this->invitableMembersForEvent($event);

        foreach ($members as $member) {
            EventInvitation::query()->firstOrCreate(
                [
                    'event_id' => $event->id,
                    'member_id' => $member->id,
                ],
                [
                    'tenant_id' => $event->tenant_id,
                    'status' => EventInvitation::STATUS_INVITED,
                    'recorded_by' => auth()->id(),
                ]
            );
        }

        $event->invitations()
            ->whereNull('response_token')
            ->get()
            ->each(fn (EventInvitation $invitation) => $invitation->update(['response_token' => EventInvitation::newResponseToken()]));

        return $members;
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
        if (! $this->eventHasConflictResource($event)) {
            return collect();
        }

        return Event::query()
            ->with(['category', 'responsibleUser'])
            ->where('tenant_id', $event->tenant_id)
            ->whereKeyNot($event->id)
            ->where('start', '<', $event->end)
            ->where('end', '>', $event->start)
            ->orderBy('start')
            ->get()
            ->filter(fn (Event $candidate) => $this->eventsShareConflictResource($event, $candidate))
            ->values();
    }

    private function normalizedEventLocation(?string $location): string
    {
        return Str::lower(trim((string) $location));
    }

    private function eventHasConflictResource(Event $event): bool
    {
        return filled($event->responsible_user_id) || $this->normalizedEventLocation($event->location) !== '';
    }

    private function eventsShareConflictResource(Event $event, Event $candidate): bool
    {
        $sameResponsible = filled($event->responsible_user_id)
            && filled($candidate->responsible_user_id)
            && (int) $event->responsible_user_id === (int) $candidate->responsible_user_id;

        $eventLocation = $this->normalizedEventLocation($event->location);
        $candidateLocation = $this->normalizedEventLocation($candidate->location);
        $sameLocation = $eventLocation !== '' && $eventLocation === $candidateLocation;

        return $sameResponsible || $sameLocation;
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
