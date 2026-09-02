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
use App\Models\Invoice;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\PublicFormSubmission;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Models\User;
use App\Services\MailTrackingService;
use App\Services\InvoiceCancellationService;
use App\Services\HtmlSanitizer;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Throwable;

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

        $dashboardData = Cache::remember(
            "tenant:{$tenantId}:dashboard:v2",
            now()->addSeconds(75),
            function () use ($tenant, $tenantId, $today) {
                return $this->buildDashboardData($tenant, $tenantId, $today);
            }
        );

        return view('dashboard', $dashboardData);
    }

    private function buildDashboardData(Tenant $tenant, int $tenantId, Carbon $today): array
    {
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();
        $onboarding = Cache::remember(
            "tenant:{$tenantId}:onboarding:v1",
            now()->addMinutes(5),
            fn () => app(OnboardingService::class)->buildForTenant($tenant)
        );

        // Kommende Events (max. 5)
        $events = Event::where('tenant_id', $tenantId)
            ->select(['id', 'tenant_id', 'title', 'start', 'end', 'location'])
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

        $entriesThisMonthCount = (clone $membersBaseQuery)
            ->whereNotNull('entry_date')
            ->whereBetween('entry_date', [$monthStart, $monthEnd])
            ->count();

        // Eintritte im aktuellen Monat
        $entries = (clone $membersBaseQuery)
            ->select(['id', 'tenant_id', 'title', 'first_name', 'last_name', 'organization', 'entry_date', 'created_at'])
            ->whereNotNull('entry_date')
            ->whereBetween('entry_date', [$monthStart, $monthEnd])
            ->latest('entry_date')
            ->take(5)
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

        $formSubmissionsBaseQuery = PublicFormSubmission::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'cancelled');
            });

        $newFormSubmissionsCount = (clone $formSubmissionsBaseQuery)
            ->where('created_at', '>=', $today->copy()->subDays(7))
            ->count();

        $latestFormSubmissions = (clone $formSubmissionsBaseQuery)
            ->select(['id', 'tenant_id', 'public_form_id', 'full_name', 'email', 'created_at'])
            ->with('form')
            ->latest()
            ->take(3)
            ->get();

        $eventBookingsBaseQuery = EventBooking::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('booking_status')
                    ->orWhere('booking_status', '!=', 'cancelled');
            });

        $pendingEventBookingsCount = (clone $eventBookingsBaseQuery)
            ->where('booking_status', 'pending')
            ->count();

        $newEventBookingsCount = (clone $eventBookingsBaseQuery)
            ->where('created_at', '>=', $today->copy()->subDays(7))
            ->count();

        $latestEventBookings = (clone $eventBookingsBaseQuery)
            ->select(['id', 'tenant_id', 'event_id', 'booker_name', 'created_at'])
            ->with('event')
            ->latest()
            ->take(3)
            ->get();

        $openInvoicesCount = Invoice::where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'open')
            ->count();

        $overdueInvoicesCount = Invoice::where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'open')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->count();

        $documentsCount = Document::where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->count();

        $documentAttentionCount = Document::where('tenant_id', $tenantId)
            ->needsAttention()
            ->count();

        // Austritte im aktuellen Monat
        $exits = (clone $membersBaseQuery)
            ->select(['id', 'tenant_id', 'title', 'first_name', 'last_name', 'organization', 'exit_date'])
            ->whereNotNull('exit_date')
            ->whereBetween('exit_date', [$monthStart, $monthEnd])
            ->latest('exit_date')
            ->take(5)
            ->get();

        // Geburtstage heute
        $birthdays = (clone $membersBaseQuery)
            ->select(['id', 'tenant_id', 'title', 'first_name', 'last_name', 'organization', 'birthday'])
            ->whereNotNull('birthday')
            ->whereMonth('birthday', $today->month)
            ->whereDay('birthday', $today->day)
            ->take(10)
            ->get();

        // Jubiläen (5, 10, 25, 50 Jahre)
        $anniversaries = (clone $membersBaseQuery)
            ->select(['id', 'tenant_id', 'title', 'first_name', 'last_name', 'organization', 'entry_date'])
            ->whereNotNull('entry_date')
            ->whereMonth('entry_date', $today->month)
            ->whereDay('entry_date', $today->day)
            ->where(function ($query) use ($today) {
                foreach ([5, 10, 25, 50] as $years) {
                    $query->orWhereYear('entry_date', $today->year - $years);
                }
            })
            ->get();

        return compact(
            'events',
            'timeline',
            'tenant',
            'membersCount',
            'licenseType',
            'entries',
            'entriesThisMonthCount',
            'entriesThisYearCount',
            'exits',
            'birthdays',
            'anniversaries',
            'onboarding',
            'upcomingEventsCount',
            'publicEventsCount',
            'formsCount',
            'newFormSubmissionsCount',
            'latestFormSubmissions',
            'pendingEventBookingsCount',
            'newEventBookingsCount',
            'latestEventBookings',
            'openInvoicesCount',
            'overdueInvoicesCount',
            'documentsCount',
            'documentAttentionCount'
        );
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
        $search = trim((string) request('search', ''));
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
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('title', 'like', '%' . $search . '%')
                        ->orWhere('location', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhereHas('responsibleUser', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderBy('start')
            ->get();

        $events = $this->attachConflictState($events);

        if ($conflictsOnly) {
            $events = $events->filter(fn (Event $event) => ($event->conflict_count ?? 0) > 0)->values();
        }

        $calendarDays = collect();
        $calendarYearMonths = collect();
        $availableDays = collect();
        $cursor = $rangeStart->copy();

        while ($view === 'month' && $cursor <= $rangeEnd) {
            $daySlot = $cursor->copy();
            $dayEvents = $events->filter(fn (Event $event) => $event->start->isSameDay($daySlot))->values();
            $isCurrentMonth = $daySlot->month === $calendarMonth->month;
            $isPast = $daySlot->lt(now()->startOfDay());
            $isAvailable = $isCurrentMonth && ! $isPast && $dayEvents->isEmpty();

            $calendarDays->push([
                'date' => $daySlot,
                'events' => $dayEvents,
                'isCurrentMonth' => $isCurrentMonth,
                'isToday' => $daySlot->isToday(),
                'isPast' => $isPast,
                'isAvailable' => $isAvailable,
            ]);

            if ($isAvailable) {
                $availableDays->push($daySlot);
            }

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
            'availableDays' => $availableDays,
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
                'search' => $search,
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
        $plannedDate = request('date')
            ? Carbon::parse(request('date'))->startOfDay()
            : null;
        $plannedStart = $plannedDate?->copy()->setTime(19, 0);
        $plannedEnd = $plannedStart?->copy()->addHours(2);

        return view('events.create', [
            'event' => new Event([
                'is_public' => true,
                'booking_enabled' => false,
                'attendance_enabled' => false,
                'response_required' => false,
                'counts_toward_required_hours' => false,
                'reminders_enabled' => false,
                'start' => $plannedStart,
                'end' => $plannedEnd,
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

        $events->each(function (Event $seriesEvent) use ($validated) {
            $this->syncBookingForm($seriesEvent, $validated['booking_address_tone'] ?? null);
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
            'organization_bookings_free' => 'nullable|boolean',
            'attendance_enabled' => 'nullable|boolean',
            'response_required' => 'nullable|boolean',
            'counts_toward_required_hours' => 'nullable|boolean',
            'reminders_enabled' => 'nullable|boolean',
            'price_per_person' => 'nullable|numeric|min:0',
            'member_price_per_person' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'max_participants_per_booking' => 'nullable|integer|min:1|max:50',
            'booking_address_tone' => ['nullable', Rule::in(['du', 'sie'])],
            'image'       => 'nullable|image|max:5120',
        ];
    }

    private function eventDataFromRequest(array $validated, Request $request, ?Event $event = null): array
    {
        return [
            'title'       => $validated['title'],
            'description' => app(HtmlSanitizer::class)->sanitize($validated['description'] ?? null),
            'location'    => $validated['location'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'] ?? null,
            'target_tag_id' => $validated['target_tag_id'] ?? null,
            'start'       => $validated['start'],
            'end'         => $validated['end'],
            'is_public'   => (bool) $validated['is_public'],
            'booking_enabled' => $request->boolean('booking_enabled'),
            'organization_bookings_free' => $request->boolean('booking_enabled') && $request->boolean('organization_bookings_free'),
            'attendance_enabled' => $request->boolean('attendance_enabled'),
            'response_required' => $request->boolean('response_required'),
            'counts_toward_required_hours' => $request->boolean('counts_toward_required_hours'),
            'reminders_enabled' => $request->boolean('reminders_enabled'),
            'price_per_person' => $request->boolean('booking_enabled') ? ($validated['price_per_person'] ?? 0) : 0,
            'member_price_per_person' => $request->boolean('booking_enabled') ? ($validated['member_price_per_person'] ?? 0) : 0,
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
        if ($event->booking_enabled) {
            $this->syncBookingForm($event);
        }
        $event->load(['activeBookingForm.fields', 'category.defaultTargetTag', 'targetTag', 'tenant', 'responsibleUser', 'creator', 'updater', 'changeLogs.user']);
        $event->setAttribute('conflicting_events', $this->findConflictingEvents($event));
        $event->setAttribute('conflict_count', $event->conflicting_events->count());

        return view('events.edit', [
            'event' => $event,
            'categories' => EventCategory::query()->with('defaultTargetTag')->orderBy('name')->get(),
            'targetTags' => Tag::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
            'users' => User::query()->where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(),
            'bookingFieldTypes' => $this->bookingFieldTypeLabels(),
            'bookingSystemFieldSlugs' => $this->eventBookingSystemFieldSlugs(),
            ...$this->participantSummaryData($event),
            ...$this->shiftSummaryData($event),
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

        $this->syncBookingForm($event, $validated['booking_address_tone'] ?? null);
        $this->logEventChange($event, 'updated', $before, $event->fresh()->toArray(), $this->buildUpdateSummary($before, $event->fresh()->toArray()));

        return redirect()->route('events.edit', $event)->with('success', 'Event aktualisiert.');
    }

    public function storeBookingField(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $form = $this->editableBookingForm($event);
        $validated = $this->validateBookingField($request, $form);
        $validated['slug'] = $this->uniqueBookingFieldSlug($form, ($validated['slug'] ?? '') ?: $validated['label']);
        $validated['sort_order'] = ($form->fields()->max('sort_order') ?? 0) + 1;

        $form->fields()->create($this->normalizeBookingFieldPayload($validated));

        return redirect()
            ->to(route('events.edit', $event) . '#anmeldefelder')
            ->with('success', 'Anmeldefeld wurde hinzugefügt.');
    }

    public function updateBookingField(Request $request, Event $event, PublicFormField $field)
    {
        $this->authorizeEvent($event);

        $form = $this->editableBookingForm($event);
        $this->authorizeBookingField($form, $field);
        abort_if($field->slug !== 'organization' && in_array($field->slug, $this->eventBookingSystemFieldSlugs(), true), 403, 'Standardfelder können nicht bearbeitet werden.');

        $validated = $this->validateBookingField($request, $form, $field);
        $validated['slug'] = $this->uniqueBookingFieldSlug($form, ($validated['slug'] ?? '') ?: $validated['label'], $field->id);

        $field->update($this->normalizeBookingFieldPayload($validated));

        return redirect()
            ->to(route('events.edit', $event) . '#anmeldefelder')
            ->with('success', 'Anmeldefeld wurde aktualisiert.');
    }

    public function moveBookingField(Request $request, Event $event, PublicFormField $field)
    {
        $this->authorizeEvent($event);

        $form = $this->editableBookingForm($event);
        $this->authorizeBookingField($form, $field);
        abort_if(in_array($field->slug, $this->eventBookingSystemFieldSlugs(), true), 403, 'Standardfelder können nicht sortiert werden.');

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $customFields = $form->fields()
            ->whereNotIn('slug', $this->eventBookingSystemFieldSlugs())
            ->orderBy('sort_order')
            ->get()
            ->values();
        $currentIndex = $customFields->search(fn (PublicFormField $item) => $item->id === $field->id);

        if ($currentIndex === false) {
            abort(404);
        }

        $swapIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if (!isset($customFields[$swapIndex])) {
            return redirect()
                ->to(route('events.edit', $event) . '#anmeldefeld-' . $field->id)
                ->with('success', 'Die Reihenfolge ist bereits passend.');
        }

        $swapField = $customFields[$swapIndex];
        $currentOrder = $field->sort_order;

        $field->update(['sort_order' => $swapField->sort_order]);
        $swapField->update(['sort_order' => $currentOrder]);

        $this->normalizeBookingFieldSortOrder($form);

        return redirect()
            ->to(route('events.edit', $event) . '#anmeldefeld-' . $field->id)
            ->with('success', 'Die Reihenfolge der Anmeldefelder wurde aktualisiert.');
    }

    public function destroyBookingField(Event $event, PublicFormField $field)
    {
        $this->authorizeEvent($event);

        $form = $this->editableBookingForm($event);
        $this->authorizeBookingField($form, $field);
        abort_if(in_array($field->slug, $this->eventBookingSystemFieldSlugs(), true), 403, 'Standardfelder können nicht gelöscht werden.');

        $field->delete();
        $this->normalizeBookingFieldSortOrder($form);

        return redirect()
            ->to(route('events.edit', $event) . '#anmeldefelder')
            ->with('success', 'Anmeldefeld wurde gelöscht.');
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

    public function participants(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant', 'activeBookingForm.fields', 'category', 'targetTag', 'responsibleUser']);

        return view('events.participants', [
            'event' => $event,
            ...$this->participantViewData($event),
        ]);
    }

    public function participantMailForm(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant']);

        $participants = $this->participantMailRecipients($event)->get();
        $templates = Template::query()
            ->where('tenant_id', $event->tenant_id)
            ->whereIn('type', [Template::TYPE_MAIL, Template::TYPE_MAIL_AND_LETTER])
            ->orderBy('name')
            ->get(['id', 'name', 'subject', 'body', 'type']);
        $participantsWithoutEmail = EventBookingParticipant::query()
            ->whereHas('booking', fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('tenant_id', $event->tenant_id)
                ->where(fn ($statusQuery) => $statusQuery
                    ->where('booking_status', '!=', 'cancelled')
                    ->orWhereNull('booking_status')))
            ->where(function ($query) {
                $query->whereNull('email')->orWhere('email', '');
            })
            ->count();

        return view('events.participant-mail', [
            'event' => $event,
            'participants' => $participants,
            'templates' => $templates,
            'participantsWithoutEmail' => $participantsWithoutEmail,
            'defaultSubject' => 'Information zu ' . $event->title,
            'defaultBody' => "<p>Hallo {{ teilnehmer_name }},</p><p>hier eine kurze Information zu <strong>{{ event_titel }}</strong> am {{ event_datum }}.</p><p>Viele Grüße<br>{{ verein_name }}</p>",
            'defaultTestEmail' => auth()->user()->email,
        ]);
    }

    public function sendParticipantTestMail(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $tenant = $event->tenant ?: auth()->user()->tenant;

        $validated = $request->validate([
            'template_id' => [
                'nullable',
                Rule::exists('templates', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $event->tenant_id)
                    ->whereIn('type', [Template::TYPE_MAIL, Template::TYPE_MAIL_AND_LETTER])),
            ],
            'test_email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:500000'],
        ], [
            'test_email.required' => 'Bitte gib eine Testadresse ein.',
            'test_email.email' => 'Bitte gib eine gültige Testadresse ein.',
        ]);

        $selectedIds = collect($request->input('participant_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $previewParticipant = null;

        if ($selectedIds->isNotEmpty()) {
            $previewParticipant = $this->participantMailRecipients($event)
                ->whereIn('id', $selectedIds)
                ->first();
        }

        $previewParticipant ??= $this->participantMailRecipients($event)->first();
        $previewParticipant ??= new EventBookingParticipant([
            'participant_type' => 'guest',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => $validated['test_email'],
        ]);

        $html = $this->renderParticipantMailHtml($event, $previewParticipant, $validated['body'], $validated['test_email']);
        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;
        $subject = '[Test] ' . $validated['subject'];

        $dispatchLog = TemplateDispatchLog::create([
            'tenant_id' => $tenant->id,
            'template_id' => $validated['template_id'] ?? null,
            'created_by' => auth()->id(),
            'channel' => 'mail',
            'action' => 'event_participant_test',
            'recipient_type' => 'event_test',
            'member_id' => $previewParticipant->exists ? $previewParticipant->member_id : null,
            'contact_id' => $previewParticipant->exists ? $previewParticipant->contact_id : null,
            'recipient_name' => 'Testmail',
            'recipient_reference' => $validated['test_email'],
            'subject' => $subject,
            'message_excerpt' => Str::limit(strip_tags($html), 240),
            'dispatched_at' => now(),
            'meta' => [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'participant_id' => $previewParticipant->exists ? $previewParticipant->id : null,
                'source' => 'event_participant_test',
                'is_test' => true,
            ],
        ]);

        $trackedHtml = app(MailTrackingService::class)->instrument($html, $dispatchLog);

        try {
            Mail::send('mail.layout', [
                'body' => $trackedHtml,
                'tenant' => $tenant,
            ], function ($mail) use ($validated, $subject, $fromAddress, $fromName, $replyToAddress, $tenant) {
                $mail->to($validated['test_email'], 'Testmail')
                    ->subject($subject)
                    ->from($fromAddress, $fromName);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?: $fromName);
                }
            });
        } catch (Throwable $e) {
            $dispatchLog->delete();
            report($e);

            return back()
                ->with('error', 'Die Testmail konnte nicht versendet werden. Bitte pruefe die Mail-Einstellungen.')
                ->withInput();
        }

        return back()
            ->with('success', 'Testmail wurde an ' . $validated['test_email'] . ' gesendet. Es wurden keine Teilnehmer angeschrieben.')
            ->withInput();
    }

    public function sendParticipantMail(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $tenant = $event->tenant ?: auth()->user()->tenant;

        $validated = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer'],
            'template_id' => [
                'nullable',
                Rule::exists('templates', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $event->tenant_id)
                    ->whereIn('type', [Template::TYPE_MAIL, Template::TYPE_MAIL_AND_LETTER])),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:500000'],
            'recipient_count_confirmation' => ['required', 'integer', 'min:1'],
            'send_confirmed' => ['accepted'],
        ], [
            'participant_ids.required' => 'Bitte waehle mindestens einen Teilnehmer aus.',
            'recipient_count_confirmation.required' => 'Bitte bestaetige die Empfaengerzahl.',
            'send_confirmed.accepted' => 'Bitte bestaetige den bewussten Versand an die ausgewaehlten Teilnehmer.',
        ]);

        $selectedIds = collect($validated['participant_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $participants = $this->participantMailRecipients($event)
            ->whereIn('id', $selectedIds)
            ->get();

        if ($participants->count() !== $selectedIds->count()) {
            return back()
                ->withErrors(['participant_ids' => 'Mindestens ein ausgewaehlter Teilnehmer gehoert nicht zu dieser Veranstaltung oder hat keine gueltige E-Mail-Adresse.'])
                ->withInput();
        }

        if ((int) $validated['recipient_count_confirmation'] !== $participants->count()) {
            return back()
                ->withErrors(['recipient_count_confirmation' => 'Die bestaetigte Empfaengerzahl stimmt nicht mit der Auswahl ueberein.'])
                ->withInput();
        }

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;
        $sentCount = 0;
        $failedCount = 0;

        foreach ($participants as $participant) {
            $html = $this->renderParticipantMailHtml($event, $participant, $validated['body']);
            $dispatchLog = TemplateDispatchLog::create([
                'tenant_id' => $tenant->id,
                'template_id' => $validated['template_id'] ?? null,
                'created_by' => auth()->id(),
                'channel' => 'mail',
                'action' => 'event_participant_mail',
                'recipient_type' => 'event_participant',
                'member_id' => $participant->member_id,
                'contact_id' => $participant->contact_id,
                'recipient_name' => $participant->display_name ?: $participant->full_name ?: $participant->email,
                'recipient_reference' => $participant->email,
                'subject' => $validated['subject'],
                'message_excerpt' => Str::limit(strip_tags($html), 240),
                'dispatched_at' => now(),
                'meta' => [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'booking_id' => $participant->event_booking_id,
                    'booking_reference' => $participant->booking?->booking_reference,
                    'participant_id' => $participant->id,
                    'source' => 'event_participants',
                ],
            ]);

            $trackedHtml = app(MailTrackingService::class)->instrument($html, $dispatchLog);

            try {
                Mail::send('mail.layout', [
                    'body' => $trackedHtml,
                    'tenant' => $tenant,
                ], function ($mail) use ($participant, $validated, $fromAddress, $fromName, $replyToAddress, $tenant) {
                    $mail->to($participant->email, $participant->display_name ?: $participant->full_name ?: null)
                        ->subject($validated['subject'])
                        ->from($fromAddress, $fromName);

                    if ($replyToAddress) {
                        $mail->replyTo($replyToAddress, $tenant->name ?: $fromName);
                    }
                });

                $sentCount++;
            } catch (Throwable $e) {
                $dispatchLog->delete();
                report($e);
                $failedCount++;
            }
        }

        $message = $sentCount . ' Teilnehmermail' . ($sentCount === 1 ? '' : 's') . ' gesendet.';

        if ($failedCount > 0) {
            $message .= ' ' . $failedCount . ' Versand' . ($failedCount === 1 ? '' : 'e') . ' fehlgeschlagen.';
        }

        return redirect()
            ->route('events.participants.manage', $event)
            ->with($failedCount > 0 ? 'error' : 'success', $message);
    }

    public function schedule(Event $event)
    {
        $this->authorizeEvent($event);
        $event->load(['tenant', 'category', 'targetTag', 'responsibleUser', 'shifts.assignments.member']);

        return view('events.schedule', [
            'event' => $event,
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
        $fieldLabels = $event->activeBookingForm?->fields
            ?->reject(fn (PublicFormField $field) => $field->isDisplayOnly())
            ->pluck('label', 'slug') ?? collect();

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

        return $pdf->download($filename);
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

    public function updateBooking(Request $request, Event $event, EventBooking $booking, InvoiceCancellationService $invoiceCancellationService)
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

        if ($validated['booking_status'] === 'cancelled' && $validated['payment_status'] === 'open') {
            $validated['payment_status'] = 'cancelled';
        }

        $booking->update([
            'booking_status' => $validated['booking_status'],
            'payment_status' => $validated['payment_status'],
        ]);

        $invoiceWasCancelled = false;

        if ($validated['booking_status'] === 'cancelled') {
            $invoiceWasCancelled = $invoiceCancellationService->cancelForEventBookingIfPossible($booking);
        }

        return redirect()
            ->route('events.participants.manage', $event)
            ->with('success', $invoiceWasCancelled
                ? 'Buchung wurde storniert. Die automatisch erzeugte Rechnung wurde ebenfalls storniert.'
                : 'Buchungs- und Zahlungsstatus wurden aktualisiert.');
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
            ->route('events.participants.manage', $event)
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
            ->route('events.participants.manage', $event)
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
            'organization_booking_type' => [
                Rule::requiredIf(fn () => $request->input('participant_type') === 'guest' && $request->input('guest_mode', 'person') === 'organization'),
                'nullable',
                Rule::in(['club', 'business', 'organization']),
            ],
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
        $defaultPriceAmount = $event->priceForParticipantType($validated['participant_type']);
        if (($validated['participant_type'] ?? null) === 'guest'
            && ($validated['guest_mode'] ?? 'person') === 'organization'
            && ($validated['organization_booking_type'] ?? null) === 'club'
            && $event->organization_bookings_free) {
            $defaultPriceAmount = 0;
        }
        $paymentRequired = $request->has('payment_required')
            ? $request->boolean('payment_required')
            : (($validated['payment_status'] ?? null) !== 'not_required' && $defaultPriceAmount > 0);
        $priceAmount = $paymentRequired ? round((float) ($validated['price_amount'] ?? $defaultPriceAmount), 2) : 0;
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
            'gross_amount' => $priceAmount * $participants->count(),
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
                'answers' => array_filter([
                    'organization_booking_type' => $validated['organization_booking_type'] ?? null,
                ]),
            ]));
        });

        $booking->recalculateTotalsFromParticipants();

        return redirect()
            ->route('events.participants.manage', $event)
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

        return redirect()->route('events.schedule.manage', $event)->with('success', 'Schicht wurde angelegt.');
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

        return redirect()->route('events.schedule.manage', $event)->with('success', 'Schicht wurde aktualisiert.');
    }

    public function destroyShift(Event $event, EventShift $shift)
    {
        $this->authorizeEvent($event);
        $this->authorizeShift($event, $shift);

        $shift->delete();

        return redirect()->route('events.schedule.manage', $event)->with('success', 'Schicht wurde gelöscht.');
    }

    public function storeShiftAssignment(Request $request, Event $event, EventShift $shift)
    {
        $this->authorizeEvent($event);
        $this->authorizeShift($event, $shift);

        $validated = $request->validate([
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:members,id',
            'member_id' => 'nullable|exists:members,id',
            'helper_name' => 'nullable|string|max:255',
            'helper_email' => 'nullable|email|max:255',
            'helper_phone' => 'nullable|string|max:255',
            'status' => 'required|in:planned,confirmed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ]);

        $memberIds = collect($validated['member_ids'] ?? [])
            ->when(!blank($validated['member_id'] ?? null), fn ($ids) => $ids->push((int) $validated['member_id']))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($memberIds->isEmpty() && blank($validated['helper_name'] ?? null)) {
            return redirect()->route('events.schedule.manage', $event)->withErrors([
                'member_ids' => 'Bitte mindestens ein Mitglied auswählen oder eine externe Helferperson eintragen.',
            ]);
        }

        $createdAssignments = 0;

        if ($memberIds->isNotEmpty()) {
            $members = Member::query()
                ->where('tenant_id', $event->tenant_id)
                ->whereIn('id', $memberIds)
                ->pluck('id');

            abort_if($members->count() !== $memberIds->count(), 404);

            $alreadyAssignedMemberIds = $shift->assignments()
                ->whereIn('member_id', $members)
                ->where('status', '!=', 'cancelled')
                ->pluck('member_id');

            $members
                ->diff($alreadyAssignedMemberIds)
                ->each(function (int $memberId) use ($shift, $event, $validated, &$createdAssignments) {
                    $shift->assignments()->create([
                        'tenant_id' => $event->tenant_id,
                        'event_id' => $event->id,
                        'member_id' => $memberId,
                        'status' => $validated['status'],
                        'notes' => $validated['notes'] ?? null,
                    ]);

                    $createdAssignments++;
                });
        }

        if (!blank($validated['helper_name'] ?? null)) {
            $shift->assignments()->create([
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'helper_name' => $validated['helper_name'] ?? null,
                'helper_email' => $validated['helper_email'] ?? null,
                'helper_phone' => $validated['helper_phone'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $createdAssignments++;
        }

        if ($createdAssignments === 0) {
            return redirect()->route('events.schedule.manage', $event)->withErrors([
                'member_ids' => 'Die ausgewählten Mitglieder sind dieser Schicht bereits zugeordnet.',
            ]);
        }

        return redirect()->route('events.schedule.manage', $event)->with('success', $createdAssignments === 1
            ? 'Eine Helferzuordnung wurde gespeichert.'
            : $createdAssignments . ' Helferzuordnungen wurden gespeichert.');
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

        return redirect()->route('events.schedule.manage', $event)->with('success', 'Zuordnung wurde entfernt.');
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

    private function syncBookingForm(Event $event, ?string $addressTone = null): void
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
        $addressTone = in_array($addressTone, ['du', 'sie'], true)
            ? $addressTone
            : ($form?->booking_address_tone ?: 'du');

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
                'booking_address_tone' => $addressTone,
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
            'description' => $this->eventBookingDescription($event, $addressTone),
            'success_message' => $this->eventBookingSuccessMessage($event, $addressTone),
            'booking_address_tone' => $addressTone,
        ]);

        $existingFields = $form->fields()->get()->keyBy('slug');
        $requiredFields = [
            ['label' => 'Unternehmen, Organisation oder Verein', 'slug' => 'organization', 'field_type' => 'text', 'is_required' => (bool) ($existingFields['organization']->is_required ?? false), 'help_text' => 'Optional: Wenn nicht eine einzelne Person, sondern eine Organisation angemeldet wird.', 'placeholder' => 'z. B. Musterverein e.V.'],
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

    private function editableBookingForm(Event $event): PublicForm
    {
        abort_unless($event->booking_enabled, 422, 'Schalte die Anmeldung zuerst im Termin ein.');

        $event->loadMissing('activeBookingForm.fields');

        if (!$event->activeBookingForm) {
            $this->syncBookingForm($event);
            $event->load('activeBookingForm.fields');
        }

        return $event->activeBookingForm ?: abort(404);
    }

    private function bookingFieldTypeLabels(): array
    {
        return [
            'text' => 'Kurze Antwort',
            'textarea' => 'Lange Antwort',
            'heading' => 'Überschrift',
            'content' => 'Textblock',
            'divider' => 'Trennlinie',
            'select' => 'Auswahlliste',
            'radio' => 'Einfachauswahl',
            'checkbox_group' => 'Mehrfachauswahl',
            'checkbox' => 'Checkbox',
            'email' => 'E-Mail',
            'iban' => 'IBAN',
            'number' => 'Zahl',
            'date' => 'Datum',
        ];
    }

    private function eventBookingSystemFieldSlugs(): array
    {
        return [
            'first_name',
            'organization',
            'last_name',
            'email',
            'phone',
            'mobile',
            'street',
            'zip',
            'city',
            'country',
            'participant_count',
            'participant_notes',
        ];
    }

    private function validateBookingField(Request $request, PublicForm $form, ?PublicFormField $field = null): array
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('public_form_fields', 'slug')
                    ->where('public_form_id', $form->id)
                    ->ignore($field?->id),
            ],
            'field_type' => ['required', Rule::in(array_keys($this->bookingFieldTypeLabels()))],
            'help_text' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
        ]) + [
            'is_required' => $request->boolean('is_required'),
        ];

        if (blank($validated['label'] ?? null) && ($validated['field_type'] ?? null) === 'divider') {
            $validated['label'] = 'Trennlinie';
        }

        if (blank($validated['label'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'label' => 'Bitte gib eine Bezeichnung ein.',
            ]);
        }

        $slug = Str::slug(($validated['slug'] ?? '') ?: $validated['label'], '_');

        if ($slug !== 'organization' && in_array($slug, $this->eventBookingSystemFieldSlugs(), true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'slug' => 'Dieser interne Kurzname ist für Standardfelder reserviert.',
            ]);
        }

        if (in_array($validated['field_type'], ['select', 'radio', 'checkbox_group'], true)
            && blank($this->normalizeBookingOptions($validated['options'] ?? null))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'options' => 'Bitte lege für dieses Auswahlfeld mindestens eine Option an.',
            ]);
        }

        return $validated;
    }

    private function normalizeBookingFieldPayload(array $validated): array
    {
        $validated['options'] = $this->normalizeBookingOptions($validated['options'] ?? null);

        if (!in_array($validated['field_type'], ['select', 'radio', 'checkbox_group'], true)) {
            $validated['options'] = null;
        }

        if (in_array($validated['field_type'], PublicFormField::displayOnlyTypes(), true)) {
            $validated['placeholder'] = null;
            $validated['options'] = null;
            $validated['is_required'] = false;
        }

        if ($validated['field_type'] === 'checkbox' && blank($validated['help_text'] ?? null)) {
            $validated['help_text'] = 'Ich stimme zu.';
        }

        return $validated;
    }

    private function normalizeBookingOptions(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $options = collect(preg_split('/\r\n|\r|\n|\|/', $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();

        return $options->isEmpty() ? null : $options->implode('|');
    }

    private function uniqueBookingFieldSlug(PublicForm $form, string $value, ?int $ignoreFieldId = null): string
    {
        $base = Str::slug($value ?: 'feld', '_') ?: 'feld';
        $slug = $base;
        $counter = 2;

        while (
            $form->fields()
                ->when($ignoreFieldId, fn ($query) => $query->where('id', '!=', $ignoreFieldId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '_' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function authorizeBookingField(PublicForm $form, PublicFormField $field): void
    {
        abort_if((int) $field->public_form_id !== (int) $form->id, 404);
    }

    private function normalizeBookingFieldSortOrder(PublicForm $form): void
    {
        $form->fields()
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->each(function (PublicFormField $field, int $index) {
                $field->update(['sort_order' => $index + 1]);
            });
    }

    private function participantViewData(Event $event): array
    {
        $participantSearch = trim((string) request('teilnehmer_suche', ''));
        $participantPaymentStatus = request('zahlstatus');
        $participantType = request('teilnehmertyp');
        $participantDisplayMode = request('anzeige') === 'organization' ? 'organization' : 'person';

        $participants = EventBookingParticipant::query()
            ->whereHas('booking', fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('tenant_id', $event->tenant_id))
            ->with(['booking.submission', 'member', 'contact'])
            ->when($participantSearch !== '', function ($query) use ($participantSearch) {
                $query->where(function ($subQuery) use ($participantSearch) {
                    $subQuery
                        ->where('first_name', 'like', '%'.$participantSearch.'%')
                        ->orWhere('last_name', 'like', '%'.$participantSearch.'%')
                        ->orWhere('organization_name', 'like', '%'.$participantSearch.'%')
                        ->orWhere('email', 'like', '%'.$participantSearch.'%')
                        ->orWhere('phone', 'like', '%'.$participantSearch.'%')
                        ->orWhereHas('booking', fn ($bookingQuery) => $bookingQuery
                            ->where('booking_reference', 'like', '%'.$participantSearch.'%')
                            ->orWhere('booker_name', 'like', '%'.$participantSearch.'%')
                            ->orWhere('booker_email', 'like', '%'.$participantSearch.'%'));
                });
            })
            ->when(filled($participantPaymentStatus), fn ($query) => $query->where('payment_status', $participantPaymentStatus))
            ->when(filled($participantType), fn ($query) => $query->where('participant_type', $participantType))
            ->orderByDesc('event_booking_id')
            ->orderBy('position')
            ->paginate(50, ['*'], 'teilnehmer')
            ->withQueryString();

        $bookingTotals = EventBooking::query()
            ->where('event_id', $event->id)
            ->where('tenant_id', $event->tenant_id)
            ->selectRaw('COUNT(*) as booking_count, COALESCE(SUM(participant_count), 0) as participant_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        return [
            'eventParticipants' => $participants,
            'bookingSubmissionCount' => (int) ($bookingTotals->booking_count ?? 0),
            'participantCount' => (int) ($bookingTotals->participant_count ?? 0),
            'bookingRevenue' => (float) ($bookingTotals->revenue ?? 0),
            'participantFilters' => [
                'search' => $participantSearch,
                'payment_status' => $participantPaymentStatus,
                'type' => $participantType,
                'display' => $participantDisplayMode,
            ],
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

    private function participantSummaryData(Event $event): array
    {
        $bookingTotals = EventBooking::query()
            ->where('event_id', $event->id)
            ->where('tenant_id', $event->tenant_id)
            ->selectRaw('COUNT(*) as booking_count, COALESCE(SUM(participant_count), 0) as participant_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        return [
            'bookingSubmissionCount' => (int) ($bookingTotals->booking_count ?? 0),
            'participantCount' => (int) ($bookingTotals->participant_count ?? 0),
            'bookingRevenue' => (float) ($bookingTotals->revenue ?? 0),
        ];
    }

    private function shiftSummaryData(Event $event): array
    {
        return [
            'eventShiftCount' => $event->shifts()
                ->where('tenant_id', $event->tenant_id)
                ->count(),
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

    private function participantMailRecipients(Event $event)
    {
        return EventBookingParticipant::query()
            ->whereHas('booking', fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('tenant_id', $event->tenant_id)
                ->where(fn ($statusQuery) => $statusQuery
                    ->where('booking_status', '!=', 'cancelled')
                    ->orWhereNull('booking_status')))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->with(['booking', 'member', 'contact'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('organization_name');
    }

    private function renderParticipantMailHtml(Event $event, EventBookingParticipant $participant, string $body, ?string $fallbackEmail = null): string
    {
        $event->loadMissing('tenant');
        $participant->loadMissing('booking');

        $eventDate = $event->start
            ? $event->start->format('d.m.Y H:i') . ' Uhr'
            : 'Termin folgt';
        $participantName = $participant->display_name ?: $participant->full_name ?: 'Teilnehmer';

        $html = strtr($body, [
            '{{ teilnehmer_name }}' => $participantName,
            '{{ teilnehmer_email }}' => (string) ($participant->email ?: $fallbackEmail ?: ''),
            '{{ event_titel }}' => (string) $event->title,
            '{{ event_datum }}' => $eventDate,
            '{{ event_ort }}' => (string) ($event->location ?: 'Ort folgt'),
            '{{ buchungsnummer }}' => (string) ($participant->booking?->booking_reference ?: ''),
            '{{ verein_name }}' => (string) ($event->tenant?->name ?: 'Clubano'),
        ]);

        if ($html === strip_tags($html)) {
            $html = nl2br(e($html), false);
        }

        return $this->sanitizeParticipantMailBody($html);
    }

    private function sanitizeParticipantMailBody(string $body): string
    {
        return app(HtmlSanitizer::class)->sanitize($body) ?? '';
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

    private function eventBookingDescription(Event $event, string $addressTone = 'du'): string
    {
        $formal = $addressTone === 'sie';
        $base = $formal
            ? 'Melden Sie sich hier verbindlich zur Veranstaltung an.'
            : 'Melde dich hier verbindlich zur Veranstaltung an.';

        if (!$event->booking_enabled) {
            return $base;
        }

        if (!$event->is_paid) {
            return $base . ' Die Teilnahme ist kostenfrei.';
        }

        $externalPrice = (float) $event->price_per_person;
        $memberPrice = (float) $event->member_price_per_person;
        $invoiceHint = $formal
            ? ' Nach der Buchung erhalten Sie automatisch eine Rechnung per E-Mail, wenn eine Zahlung fällig ist.'
            : ' Nach der Buchung erhältst du automatisch eine Rechnung per E-Mail, wenn eine Zahlung fällig ist.';

        if ($externalPrice > 0 && $memberPrice < $externalPrice) {
            $memberText = $memberPrice > 0
                ? 'Für Mitglieder kostet die Teilnahme ' . $this->formatEventPriceForText($memberPrice, $event) . '.'
                : 'Für Mitglieder ist die Teilnahme kostenlos.';
            $organizationText = $event->organization_bookings_free
                ? ' Externe Vereine können kostenfrei teilnehmen.'
                : '';

            return $base
                . ' ' . $memberText
                . $organizationText
                . ' Für Gäste und Nichtmitglieder kostet die Teilnahme ' . $this->formatEventPriceForText($externalPrice, $event) . '.'
                . $invoiceHint;
        }

        if ($externalPrice > 0 && $event->organization_bookings_free) {
            return $base
                . ' Externe Vereine können kostenfrei teilnehmen.'
                . ' Firmen, Unternehmen, sonstige Organisationen, Gäste und Nichtmitglieder zahlen ' . $this->formatEventPriceForText($externalPrice, $event) . '.'
                . $invoiceHint;
        }

        return $base
            . ' Preis: ' . $this->formatEventPriceForText($externalPrice, $event)
            . ' pro Person.'
            . $invoiceHint;
    }

    private function eventBookingSuccessMessage(Event $event, string $addressTone = 'du'): string
    {
        if ($addressTone === 'sie') {
            return $event->is_paid
                ? 'Danke für Ihre Anmeldung. Wir haben Ihren Platz vorgemerkt und prüfen, ob eine Zahlung fällig ist.'
                : 'Danke für Ihre Anmeldung. Wir haben Ihren Platz vorgemerkt.';
        }

        return $event->is_paid
            ? 'Danke für die Anmeldung. Wir haben euren Platz vorgemerkt und prüfen, ob eine Zahlung fällig ist.'
            : 'Danke für die Anmeldung. Wir haben euren Platz vorgemerkt.';
    }

    private function formatEventPriceForText(float $amount, Event $event): string
    {
        $currency = strtoupper($event->currency ?: 'EUR');
        $suffix = $currency === 'EUR' ? '€' : $currency;

        return number_format($amount, 2, ',', '.') . ' ' . $suffix;
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
