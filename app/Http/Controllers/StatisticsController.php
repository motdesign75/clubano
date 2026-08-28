<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventCategory;
use App\Models\EventInvitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\PublicFormSubmission;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;
        $canViewFinance = $user->hasPermission('finance');
        $canManageEvents = $user->hasPermission('events');

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'category_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
        ]);

        $dateFrom = filled($validated['date_from'] ?? null)
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->startOfYear();
        $dateTo = filled($validated['date_to'] ?? null)
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfYear();
        $categoryId = filled($validated['category_id'] ?? null) ? (int) $validated['category_id'] : null;
        $memberId = filled($validated['member_id'] ?? null) ? (int) $validated['member_id'] : null;

        $categories = EventCategory::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $activeMembers = Member::query()
            ->with('tags:id,name')
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->get();

        if ($memberId && ! $activeMembers->contains('id', $memberId)) {
            $memberId = null;
        }

        $memberOptions = $activeMembers
            ->sortBy(fn (Member $member) => mb_strtolower($member->organization ?: trim(($member->last_name ?? '').' '.($member->first_name ?? ''))))
            ->values();

        $events = Event::query()
            ->with('category:id,name,color')
            ->where('tenant_id', $tenantId)
            ->whereBetween('start', [$dateFrom, $dateTo])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('start')
            ->get();

        $attendances = EventAttendance::query()
            ->with(['event:id,title,start,category_id', 'member:id,first_name,last_name,organization'])
            ->where('tenant_id', $tenantId)
            ->where('attended', true)
            ->when($memberId, fn ($query) => $query->where('member_id', $memberId))
            ->whereHas('event', fn ($query) => $query->whereBetween('start', [$dateFrom, $dateTo]))
            ->when($categoryId, fn ($query) => $query->whereHas('event', fn ($eventQuery) => $eventQuery->where('category_id', $categoryId)))
            ->get();

        $invitations = EventInvitation::query()
            ->with(['member:id,first_name,last_name,organization', 'event:id,title,start,category_id'])
            ->where('tenant_id', $tenantId)
            ->when($memberId, fn ($query) => $query->where('member_id', $memberId))
            ->whereHas('event', fn ($query) => $query->whereBetween('start', [$dateFrom, $dateTo]))
            ->when($categoryId, fn ($query) => $query->whereHas('event', fn ($eventQuery) => $eventQuery->where('category_id', $categoryId)))
            ->get();

        $submissions = PublicFormSubmission::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $openInvoices = $canViewFinance
            ? Invoice::query()
                ->where('tenant_id', $tenantId)
                ->where('document_type', 'invoice')
                ->whereIn('status', ['offen', 'open', 'sent', 'teilbezahlt'])
                ->get()
            : collect();

        $requiredHours = round((float) $activeMembers->sum('required_service_hours'), 2);
        $countedHours = round((float) $attendances->where('counts_toward_required_hours', true)->sum('hours'), 2);
        $membersWithAttendance = $attendances->pluck('member_id')->unique()->count();
        $attendanceEvents = $events->where('attendance_enabled', true)->count();
        $responses = $invitations->whereIn('status', [
            EventInvitation::STATUS_ACCEPTED,
            EventInvitation::STATUS_DECLINED,
            EventInvitation::STATUS_MAYBE,
            EventInvitation::STATUS_EXCUSED,
        ])->count();
        $acceptedInvitations = $invitations->where('status', EventInvitation::STATUS_ACCEPTED)->count();
        $declinedInvitations = $invitations->where('status', EventInvitation::STATUS_DECLINED)->count();
        $maybeInvitations = $invitations->where('status', EventInvitation::STATUS_MAYBE)->count();
        $excusedInvitations = $invitations->where('status', EventInvitation::STATUS_EXCUSED)->count();
        $openInvitations = $invitations->whereIn('status', [
            EventInvitation::STATUS_INVITED,
            EventInvitation::STATUS_NO_RESPONSE,
        ])->count();
        $attendanceRate = $acceptedInvitations > 0
            ? round(($attendances->count() / $acceptedInvitations) * 100)
            : null;
        $responseRate = $invitations->count() > 0
            ? round(($responses / $invitations->count()) * 100)
            : null;

        $eventCategoryStats = $events
            ->groupBy(fn (Event $event) => $event->category?->name ?: 'Ohne Terminart')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'count' => $items->count(),
                'attendance_enabled' => $items->where('attendance_enabled', true)->count(),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values();

        $tagStats = $activeMembers
            ->flatMap(fn (Member $member) => $member->tags->map(fn ($tag) => $tag->name))
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn ($count, $label) => ['label' => $label, 'count' => $count])
            ->values();

        $topAttendanceMembers = $attendances
            ->groupBy('member_id')
            ->map(function ($items) {
                $member = $items->first()->member;

                return [
                    'name' => $member?->organization ?: trim(($member?->first_name ?? '').' '.($member?->last_name ?? '')) ?: 'Ohne Namen',
                    'count' => $items->count(),
                    'hours' => round((float) $items->sum('hours'), 2),
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values();

        $memberParticipation = $invitations
            ->groupBy('member_id')
            ->map(function ($items, $invitationMemberId) use ($attendances) {
                $member = $items->first()->member;
                $attendedCount = $attendances->where('member_id', (int) $invitationMemberId)->count();
                $acceptedCount = $items->where('status', EventInvitation::STATUS_ACCEPTED)->count();
                $respondedCount = $items->whereIn('status', [
                    EventInvitation::STATUS_ACCEPTED,
                    EventInvitation::STATUS_DECLINED,
                    EventInvitation::STATUS_MAYBE,
                    EventInvitation::STATUS_EXCUSED,
                ])->count();

                return [
                    'name' => $member?->organization ?: trim(($member?->first_name ?? '').' '.($member?->last_name ?? '')) ?: 'Ohne Namen',
                    'invited' => $items->count(),
                    'accepted' => $acceptedCount,
                    'attended' => $attendedCount,
                    'response_rate' => $items->count() > 0 ? round(($respondedCount / $items->count()) * 100) : 0,
                    'attendance_rate' => $acceptedCount > 0 ? round(($attendedCount / $acceptedCount) * 100) : 0,
                ];
            })
            ->sortByDesc('attendance_rate')
            ->take(8)
            ->values();

        $categoryParticipation = $events
            ->groupBy(fn (Event $event) => $event->category?->name ?: 'Ohne Terminart')
            ->map(function ($items, $label) use ($invitations, $attendances) {
                $eventIds = $items->pluck('id');
                $categoryInvitations = $invitations->whereIn('event_id', $eventIds);
                $categoryAttendances = $attendances->whereIn('event_id', $eventIds);

                return [
                    'label' => $label,
                    'events' => $items->count(),
                    'invited' => $categoryInvitations->count(),
                    'accepted' => $categoryInvitations->where('status', EventInvitation::STATUS_ACCEPTED)->count(),
                    'attended' => $categoryAttendances->count(),
                ];
            })
            ->sortByDesc('events')
            ->take(6)
            ->values();

        $monthlyParticipation = collect();
        $cursor = $dateFrom->copy()->startOfMonth();
        $lastMonth = $dateTo->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $monthEventIds = $events
                ->filter(fn (Event $event) => $event->start && $event->start->betweenIncluded($monthStart, $monthEnd))
                ->pluck('id');
            $monthInvitations = $invitations->whereIn('event_id', $monthEventIds);
            $monthResponses = $monthInvitations->whereIn('status', [
                EventInvitation::STATUS_ACCEPTED,
                EventInvitation::STATUS_DECLINED,
                EventInvitation::STATUS_MAYBE,
                EventInvitation::STATUS_EXCUSED,
            ])->count();

            $monthlyParticipation->push([
                'label' => $cursor->translatedFormat('M Y'),
                'events' => $monthEventIds->count(),
                'responses' => $monthResponses,
                'attended' => $attendances->whereIn('event_id', $monthEventIds)->count(),
            ]);

            $cursor->addMonth();
        }

        $recentActivity = collect()
            ->merge($events->sortByDesc('start')->take(4)->map(fn (Event $event) => [
                'type' => 'Termin',
                'title' => $event->title,
                'meta' => $event->start?->format('d.m.Y H:i').' Uhr',
                'route' => route('events.show', $event),
            ]))
            ->merge($submissions->sortByDesc('created_at')->take(4)->map(fn (PublicFormSubmission $submission) => [
                'type' => 'Formular',
                'title' => $submission->full_name ?: $submission->email ?: 'Neue Antwort',
                'meta' => $submission->created_at?->format('d.m.Y H:i').' Uhr',
                'route' => route('forms.submissions', $submission->public_form_id),
            ]))
            ->take(6)
            ->values();

        $spotlights = collect([
            [
                'label' => 'Formularantworten',
                'value' => $submissions->count(),
                'hint' => $submissions->whereNull('member_id')->whereNull('contact_id')->count().' noch nicht übernommen',
                'route' => route('forms.index'),
            ],
            [
                'label' => 'Pflichtstunden',
                'value' => number_format($countedHours, 2, ',', '.').' h',
                'hint' => number_format(max(0, $requiredHours - $countedHours), 2, ',', '.').' h offen',
                'route' => $canManageEvents ? route('events.attendance.report') : route('members.index'),
            ],
        ]);

        if ($canViewFinance) {
            $spotlights->push([
                'label' => 'Offene Rechnungen',
                'value' => $openInvoices->count(),
                'hint' => number_format((float) $openInvoices->sum('total'), 2, ',', '.').' EUR offen',
                'route' => route('invoices.index'),
            ]);
        }

        return view('statistics.index', [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'category_id' => $categoryId,
                'member_id' => $memberId,
            ],
            'categories' => $categories,
            'memberOptions' => $memberOptions,
            'summaryCards' => [
                [
                    'label' => 'Aktive Mitglieder',
                    'value' => number_format($activeMembers->count(), 0, ',', '.'),
                    'hint' => $activeMembers->whereBetween('entry_date', [$dateFrom, $dateTo])->count().' neue Eintritte im Zeitraum',
                    'tone' => 'slate',
                ],
                [
                    'label' => 'Termine',
                    'value' => number_format($events->count(), 0, ',', '.'),
                    'hint' => $attendanceEvents.' mit Anwesenheit',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Teilnahme',
                    'value' => number_format($membersWithAttendance, 0, ',', '.'),
                    'hint' => 'Mitglieder mit erfasster Anwesenheit',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Rückmeldungen',
                    'value' => number_format($responses, 0, ',', '.'),
                    'hint' => $invitations->count() > 0 ? round(($responses / $invitations->count()) * 100).'% beantwortet' : 'noch keine Einladungen',
                    'tone' => 'amber',
                ],
            ],
            'spotlights' => $spotlights,
            'eventCategoryStats' => $eventCategoryStats,
            'tagStats' => $tagStats,
            'topAttendanceMembers' => $topAttendanceMembers,
            'participationSummary' => [
                'invited' => $invitations->count(),
                'responses' => $responses,
                'response_rate' => $responseRate,
                'accepted' => $acceptedInvitations,
                'declined' => $declinedInvitations,
                'maybe' => $maybeInvitations,
                'excused' => $excusedInvitations,
                'open' => $openInvitations,
                'attended' => $attendances->count(),
                'attendance_rate' => $attendanceRate,
            ],
            'memberParticipation' => $memberParticipation,
            'categoryParticipation' => $categoryParticipation,
            'monthlyParticipation' => $monthlyParticipation->take(12),
            'recentActivity' => $recentActivity,
        ]);
    }
}
