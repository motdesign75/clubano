@extends('layouts.app')

@section('title', 'Mitglieder')

@section('content')
@php
    $canManageMembers = auth()->user()?->canManageMembers() ?? false;
    $currentStatus = request('status', '');
    $currentExitScope = request('exit_scope', 'kuendigungen');
    $currentDisplayMode = request('display', 'auto');
    if (!in_array($currentDisplayMode, ['auto', 'person', 'organization'], true)) {
        $currentDisplayMode = 'auto';
    }
    $statuses = [
        '' => 'Alle',
        'aktiv' => 'Aktiv',
        'ehemalig' => 'Ehemalig',
        'zukünftig' => 'Zukünftig',
        'archiviert' => 'Archiviert',
    ];
    $exitScopes = [
        'kuendigungen' => 'Gekuendigt',
        'zeitraum' => 'Naechste ' . ($exitWindowDays ?? 90) . ' Tage',
        'vergangen' => 'Schon raus',
        'alle' => 'Alle mit Austritt',
    ];
    $displayModes = [
        'auto' => 'Automatisch',
        'person' => 'Personen',
        'organization' => 'Organisationen',
    ];
    $displayHeading = $currentDisplayMode === 'organization' ? 'Organisation / Kontakt' : 'Mitglied';
    $displaySecondaryHeading = $currentDisplayMode === 'organization' ? 'Kontakt & Mitgliedschaft' : 'Kontakt & Mitgliedschaft';
    $memberDisplay = function ($member) use ($currentDisplayMode) {
        $fullName = trim((string) ($member->full_name ?? ''));
        $organization = trim((string) ($member->organization ?? ''));

        if ($currentDisplayMode === 'organization') {
            return [
                'primary' => $organization !== '' ? $organization : ($fullName !== '' ? $fullName : 'Ohne Namen'),
                'secondary' => $organization !== '' && $fullName !== '' ? $fullName : null,
            ];
        }

        if ($currentDisplayMode === 'person') {
            return [
                'primary' => $fullName !== '' ? $fullName : ($organization !== '' ? $organization : 'Ohne Namen'),
                'secondary' => $organization !== '' ? $organization : null,
            ];
        }

        return [
            'primary' => $organization !== '' ? $organization : ($fullName !== '' ? $fullName : 'Ohne Namen'),
            'secondary' => $organization !== '' && $fullName !== '' ? $fullName : null,
        ];
    };
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-[28px] bg-slate-950 px-6 py-5 text-white shadow-sm sm:px-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Mitglieder</div>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Menschen im Verein</h1>
                <p class="mt-2 max-w-lg text-sm leading-6 text-slate-300">
                    Schnell sehen, finden und bearbeiten, ohne dass Nebensachen lauter sind als die Menschen selbst.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-200">
                    <div class="font-semibold text-white">{{ $stats['aktiv'] }} aktiv</div>
                    <div class="mt-0.5 text-xs text-slate-300">{{ $stats['gekuendigt'] ?? 0 }} gekuendigt, {{ $stats['zukünftig'] }} im Start</div>
                </div>

                @if($canManageMembers)
                    <a href="{{ route('members.create') }}"
                       class="inline-flex items-center justify-center rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-100">
                        Neues Mitglied
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[1.1fr_1.1fr_1fr_1fr]">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-3.5 py-3">
                <div class="flex items-baseline justify-between gap-3">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Aktiv</div>
                    <div class="text-2xl font-semibold tracking-tight text-emerald-900">{{ $stats['aktiv'] }}</div>
                </div>
                <div class="mt-1 text-xs text-emerald-700/70">Im Verein</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3.5 py-3">
                <div class="flex items-baseline justify-between gap-3">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Alle</div>
                    <div class="text-2xl font-semibold tracking-tight text-slate-950">{{ $stats['alle'] }}</div>
                </div>
                <div class="mt-1 text-xs text-slate-500">Gesamt</div>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 px-3.5 py-3">
                <div class="flex items-baseline justify-between gap-3">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-rose-700">Gekuendigt</div>
                    <div class="text-2xl font-semibold tracking-tight text-rose-900">{{ $stats['gekuendigt'] ?? 0 }}</div>
                </div>
                <div class="mt-1 text-xs text-rose-700/70">Austritt offen</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3.5 py-3">
                <div class="grid gap-2 text-xs text-slate-600 sm:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                    <div>
                        <div class="font-semibold uppercase tracking-[0.16em] text-slate-400">Zukuenftig</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $stats['zukünftig'] }}</div>
                    </div>
                    <div>
                        <div class="font-semibold uppercase tracking-[0.16em] text-slate-400">Ehemalig</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $stats['ehemalig'] }}</div>
                    </div>
                    <div>
                        <div class="font-semibold uppercase tracking-[0.16em] text-slate-400">Archiviert</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $stats['archiviert'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('members.index') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">Suche und Filter</div>
                <div class="mt-1 text-sm text-slate-500">Finde schnell die richtigen Menschen, ohne dich in Optionen zu verlieren.</div>
            </div>
            <nav class="flex flex-wrap gap-2">
                @foreach($statuses as $key => $label)
                    <a href="{{ route('members.index', array_merge(request()->except('page'), ['status' => $key])) }}"
                       class="whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-semibold transition {{ $currentStatus === $key ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="mb-4">
            <div class="mb-2 text-sm font-medium text-slate-700">Darstellung</div>
            <div class="flex flex-wrap gap-2">
                @foreach($displayModes as $key => $label)
                    <a href="{{ route('members.index', array_merge(request()->except('page'), ['display' => $key])) }}"
                       class="whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-semibold transition {{ $currentDisplayMode === $key ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Suche</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Name, E-Mail, Mitgliedsnummer, Ort ..."
                       class="w-full rounded-2xl border border-slate-300 px-4 py-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tag</label>
                <select name="tag" class="w-full rounded-2xl border border-slate-300 px-4 py-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <option value="">Alle Tags</option>
                    @foreach($allTags as $tag)
                        <option value="{{ $tag->id }}" @selected((string) request('tag') === (string) $tag->id)>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mitgliedschaft</label>
                <select name="membership" class="w-full rounded-2xl border border-slate-300 px-4 py-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <option value="">Alle Mitgliedschaften</option>
                    @foreach($memberships as $membership)
                        <option value="{{ $membership->id }}" @selected((string) request('membership') === (string) $membership->id)>{{ $membership->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sortierung</label>
                <select name="sort" class="w-full rounded-2xl border border-slate-300 px-4 py-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                    <option value="last_name" @selected($sortField === 'last_name')>Nachname</option>
                    <option value="first_name" @selected($sortField === 'first_name')>Vorname</option>
                    <option value="member_id" @selected($sortField === 'member_id')>Mitgliedsnummer</option>
                    <option value="entry_date" @selected($sortField === 'entry_date')>Eintritt</option>
                    <option value="email" @selected($sortField === 'email')>E-Mail</option>
                    <option value="city" @selected($sortField === 'city')>Ort</option>
                </select>
            </div>
        </div>

        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="display" value="{{ $currentDisplayMode }}">
        <input type="hidden" name="exit_scope" value="{{ request('exit_scope', 'kuendigungen') }}">
        <input type="hidden" name="exit_days" value="{{ (int) ($exitWindowDays ?? 90) }}">
        <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800">
                Filtern
            </button>
            <a href="{{ route('members.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">
                Filter zuruecksetzen
            </a>
        </div>
    </form>

    <details class="group rounded-3xl border border-slate-200 bg-white shadow-sm" {{ ($currentExitScope !== 'kuendigungen' || ($stats['gekuendigt'] ?? 0) > 0) ? 'open' : '' }}>
        <summary class="list-none cursor-pointer px-5 py-4 sm:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Austritte</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-2">
                        <h2 class="text-lg font-semibold tracking-tight text-slate-950">Nur bei Bedarf einblenden</h2>
                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">
                            {{ $stats['gekuendigt'] ?? 0 }} gekuendigt
                        </span>
                        @if(($stats['austritte_bald'] ?? 0) > 0)
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                                {{ $stats['austritte_bald'] }} bald faellig
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('members.index', array_merge(request()->except('page'), ['status' => 'ehemalig'])) }}"
                       class="inline-flex items-center justify-center rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50">
                        Ehemalige ansehen
                    </a>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-180">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
            </div>
        </summary>

        <div class="border-t border-slate-200 px-5 py-5 sm:px-6">
            <div class="flex flex-wrap gap-2.5">
                @foreach($exitScopes as $key => $label)
                    <a href="{{ route('members.index', array_merge(request()->except(['page', 'exit_scope']), ['exit_scope' => $key])) }}"
                       class="whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-semibold transition {{ $currentExitScope === $key ? 'bg-slate-950 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([30, 60, 90, 180] as $days)
                <a href="{{ route('members.index', array_merge(request()->except(['page', 'exit_scope', 'exit_days']), ['exit_scope' => 'zeitraum', 'exit_days' => $days])) }}"
                   class="rounded-2xl border px-4 py-3 text-left transition {{ $currentExitScope === 'zeitraum' && (int) ($exitWindowDays ?? 90) === $days ? 'border-slate-300 bg-slate-50 text-slate-950 shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                    <div class="text-sm font-semibold">Naechste {{ $days }} Tage</div>
                </a>
            @endforeach
            </div>

            @if(($upcomingExits ?? collect())->isNotEmpty())
                <div class="mt-4 grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
                    @foreach($upcomingExits as $member)
                        @php
                            $memberDisplayData = $memberDisplay($member);
                        @endphp
                        <a href="{{ route('members.show', $member) }}"
                           class="rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm transition hover:bg-slate-50">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="font-semibold text-slate-950">{{ $memberDisplayData['primary'] }}</div>
                                    @if($memberDisplayData['secondary'])
                                        <div class="mt-1 text-sm text-slate-500">{{ $memberDisplayData['secondary'] }}</div>
                                    @endif
                                    <div class="mt-1 text-sm text-slate-500">{{ $member->membership?->name ?? 'Ohne Mitgliedschaft' }}</div>
                                </div>
                                @if(optional($member->exit_date)->isFuture())
                                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">
                                        Austritt {{ optional($member->exit_date)->format('d.m.Y') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                        Ausgetreten {{ optional($member->exit_date)->format('d.m.Y') }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-3 break-words text-sm text-slate-700">{{ $member->email ?: 'keine E-Mail' }}</div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                    Fuer diesen Blick gibt es aktuell keine passenden Austritte.
                </div>
            @endif
        </div>
    </details>

    @php
        $memberIdQuery = implode(',', $filteredMemberIds ?? []);
    @endphp

    @if($canManageMembers && !empty($filteredMemberIds))
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Serienaktionen fuer die aktuelle Auswahl</div>
                    <div class="mt-1 text-sm text-slate-500">{{ count($filteredMemberIds) }} Mitglied(er) im aktuellen Ergebnis.</div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('mail.create', ['members' => $memberIdQuery]) }}"
                       class="inline-flex rounded-full bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                        Serienmail
                    </a>
                    <a href="{{ route('members.communication.export', ['type' => 'whatsapp', 'member_ids' => $memberIdQuery]) }}"
                       class="inline-flex rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        WhatsApp exportieren
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if($members->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-slate-500 shadow-sm">
            Keine Mitglieder gefunden.
        </div>
    @else
        <form method="POST" action="{{ route('members.bulk-action') }}" id="members-bulk-action-form" class="hidden">
            @csrf
        </form>

        @if($canManageMembers)
            <div class="mb-3 mt-4 flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-center">
                    <select name="action" id="members-bulk-action-select" form="members-bulk-action-form" required class="w-full rounded-2xl border-gray-300 px-3 py-2 sm:w-auto">
                        <option value="">Aktion waehlen</option>
                        <option value="set_status_aktiv">Status: Aktiv</option>
                        <option value="set_status_zukuenftig">Status: Zukuenftig</option>
                        <option value="set_status_ehemalig">Status: Ehemalig</option>
                        <option value="assign_membership">Mitgliedschaft zuweisen</option>
                        <option value="delete">Loeschen</option>
                    </select>

                    <select name="membership_id" id="members-bulk-membership-select" form="members-bulk-action-form" class="hidden w-full rounded-2xl border-gray-300 px-3 py-2 sm:w-auto">
                        <option value="">Mitgliedschaft waehlen</option>
                        @foreach($memberships as $membership)
                            <option value="{{ $membership->id }}">
                                {{ $membership->name }} · {{ number_format((float) $membership->amount, 2, ',', '.') }} € / {{ $membership->interval }}
                            </option>
                        @endforeach
                    </select>

                    <x-primary-button type="submit" form="members-bulk-action-form" class="w-full justify-center sm:w-auto">Ausfuehren</x-primary-button>

                    <div class="text-xs text-slate-500" id="members-bulk-action-hint">
                        Loeschen verschiebt markierte Mitglieder sicher ins Archiv.
                    </div>
            </div>
        @endif

            <div class="space-y-4 lg:hidden">
                @foreach($members as $member)
                    @php
                        $memberDisplayData = $memberDisplay($member);
                        $statusBadgeClass = match($member->status){
                            'aktiv' => 'bg-green-100 text-green-800',
                            'ehemalig' => 'bg-slate-100 text-slate-700',
                            'zukünftig' => 'bg-blue-100 text-blue-800',
                            'archiviert' => 'bg-amber-100 text-amber-800',
                            default => 'bg-yellow-100 text-yellow-800'
                        };
                    @endphp
                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            @if($canManageMembers)
                                <input type="checkbox" name="selected[]" value="{{ $member->id }}" form="members-bulk-action-form" class="member-checkbox mt-1">
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('members.show', $member) }}" class="font-semibold text-slate-950 hover:text-indigo-700">
                                            {{ $memberDisplayData['primary'] }}
                                        </a>
                                        @if($memberDisplayData['secondary'])
                                            <div class="mt-1 text-sm text-slate-500">{{ $memberDisplayData['secondary'] }}</div>
                                        @endif
                                        @if($member->member_id)
                                            <div class="mt-1 text-xs text-slate-500">Nr. {{ $member->member_id }}</div>
                                        @endif
                                    </div>

                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadgeClass }}">
                                        {{ ucfirst($member->status) }}
                                    </span>
                                </div>

                                <dl class="mt-4 space-y-3 text-sm">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mitgliedschaft</dt>
                                        <dd class="mt-1 text-slate-800">{{ $member->membership?->name ?? '—' }}</dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kontakt</dt>
                                        <dd class="mt-1 text-slate-800">{{ $member->email ?: '—' }}</dd>
                                        @if($member->mobile)
                                            <div class="mt-1 text-xs text-slate-500">{{ $member->mobile }}</div>
                                        @endif
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Eintritt</dt>
                                            <dd class="mt-1 text-slate-800">{{ optional($member->entry_date)->format('d.m.Y') ?: '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Austritt</dt>
                                            <dd class="mt-1 text-slate-800">{{ optional($member->exit_date)->format('d.m.Y') ?: '—' }}</dd>
                                        </div>
                                    </div>

                                    @if($member->tags->isNotEmpty())
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tags</dt>
                                            <dd class="mt-2 flex flex-wrap gap-2">
                                                @foreach($member->tags as $tag)
                                                    <span class="inline-block rounded px-2 py-1 text-xs text-white" style="background-color: {{ $tag->color ?? '#6b7280' }}">
                                                        {{ $tag->name }}
                                                    </span>
                                                @endforeach
                                            </dd>
                                        </div>
                                    @endif
                                </dl>

                                @if($canManageMembers)
                                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                                        <a href="{{ route('members.edit', $member) }}" class="font-medium text-indigo-700 hover:underline">Bearbeiten</a>
                                        <a href="{{ route('members.datenauskunft', $member) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Datenauskunft</a>
                                        <form action="{{ route('members.destroy', $member) }}" method="POST" class="inline-block" onsubmit="return confirm('Mitglied wirklich archivieren?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-slate-600 hover:text-slate-900 hover:underline">Archivieren</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="hidden overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:block">
                <div class="overflow-x-auto">
                    <div class="min-w-[1120px]">
                        <div class="grid grid-cols-[40px_minmax(260px,1.3fr)_180px_minmax(250px,1fr)_110px_96px] gap-4 border-b border-slate-200 px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <label class="flex items-center justify-center">
                                @if($canManageMembers)
                                    <input type="checkbox" id="checkAll" class="rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                                @endif
                            </label>
                            <div>{{ $displayHeading }}</div>
                            <div>{{ $displaySecondaryHeading }}</div>
                            <div>Eintritt</div>
                            <div>Status</div>
                            <div>Aktion</div>
                        </div>

                        <div class="divide-y divide-slate-100">
                    @foreach($members as $member)
                        @php
                            $memberDisplayData = $memberDisplay($member);
                            $statusBadgeClass = match($member->status){
                                'aktiv' => 'bg-green-100 text-green-800',
                                'ehemalig' => 'bg-slate-100 text-slate-700',
                                'zukünftig' => 'bg-blue-100 text-blue-800',
                                'archiviert' => 'bg-amber-100 text-amber-800',
                                default => 'bg-yellow-100 text-yellow-800'
                            };
                        @endphp
                        <article class="grid grid-cols-[40px_minmax(260px,1.3fr)_180px_minmax(250px,1fr)_110px_96px] gap-4 px-4 py-4 transition hover:bg-slate-50/70">
                            <div class="flex justify-center pt-1">
                                @if($canManageMembers)
                                    <input type="checkbox" name="selected[]" value="{{ $member->id }}" form="members-bulk-action-form" class="member-checkbox rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                                @endif
                            </div>

                            <div class="min-w-0">
                                <a href="{{ route('members.show', $member) }}" class="block break-words text-base font-semibold leading-6 text-slate-950 transition hover:text-indigo-700">
                                    {{ $memberDisplayData['primary'] }}
                                </a>
                                @if($memberDisplayData['secondary'])
                                    <div class="mt-1 break-words text-sm text-slate-500">{{ $memberDisplayData['secondary'] }}</div>
                                @endif
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    @if($member->member_id)
                                        <div>Nr. {{ $member->member_id }}</div>
                                    @endif
                                    @if($member->city)
                                        <div>{{ $member->city }}</div>
                                    @endif
                                    @if($member->exit_date)
                                        <div class="{{ optional($member->exit_date)->isFuture() ? 'text-rose-600' : 'text-slate-500' }}">
                                            Austritt {{ optional($member->exit_date)->format('d.m.Y') }}
                                        </div>
                                    @endif
                                </div>

                                @if($member->tags->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach($member->tags as $tag)
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold text-white" style="background-color: {{ $tag->color ?? '#6b7280' }}">
                                                {{ $tag->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-2 pt-0.5">
                                <div>
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kontakt</div>
                                    <div class="mt-0.5 break-words text-sm text-slate-800">{{ $member->email ?: 'Keine E-Mail' }}</div>
                                    @if($member->mobile)
                                        <div class="mt-0.5 text-xs text-slate-500">{{ $member->mobile }}</div>
                                    @endif
                                </div>

                                <div>
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Mitgliedschaft</div>
                                    <div class="mt-0.5 text-sm text-slate-800">{{ $member->membership?->name ?? '—' }}</div>
                                    @if($member->membership_amount)
                                        <div class="mt-0.5 text-xs text-slate-500">
                                            {{ number_format((float) $member->membership_amount, 2, ',', '.') }} €
                                            @if($member->membership_interval)
                                                / {{ $member->membership_interval }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Eintritt</div>
                                <div class="mt-0.5 text-sm font-medium text-slate-900">{{ optional($member->entry_date)->format('d.m.Y') ?: '—' }}</div>
                            </div>

                            <div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusBadgeClass }}">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </div>

                            <div class="pt-0.5">
                                @if($canManageMembers)
                                    <div class="flex flex-col items-start gap-2 text-xs">
                                        <a href="{{ route('members.edit', $member) }}"
                                           class="font-semibold text-indigo-700 transition hover:text-indigo-800">
                                            Bearbeiten
                                        </a>
                                        <a href="{{ route('members.datenauskunft', $member) }}"
                                           class="text-slate-500 transition hover:text-slate-900">
                                            Datenauskunft
                                        </a>
                                        <form action="{{ route('members.destroy', $member) }}" method="POST" class="inline-block" onsubmit="return confirm('Mitglied wirklich archivieren?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-slate-500 transition hover:text-slate-900">
                                                Archivieren
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                        </div>
                    </div>
                </div>
            </div>

        <div class="mt-4">
            {{ $members->appends(request()->query())->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.member-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    const bulkActionForm = document.getElementById('members-bulk-action-form');
    const bulkActionSelect = document.getElementById('members-bulk-action-select');
    const bulkMembershipSelect = document.getElementById('members-bulk-membership-select');
    const bulkActionHint = document.getElementById('members-bulk-action-hint');

    if (bulkActionForm && bulkActionSelect) {
        const syncBulkActionState = () => {
            const action = bulkActionSelect.value;
            const needsMembership = action === 'assign_membership';

            if (bulkMembershipSelect) {
                bulkMembershipSelect.classList.toggle('hidden', !needsMembership);
                bulkMembershipSelect.required = needsMembership;

                if (!needsMembership) {
                    bulkMembershipSelect.value = '';
                }
            }

            if (bulkActionHint) {
                bulkActionHint.textContent = needsMembership
                    ? 'Die gewaehlte Mitgliedschaft wird inkl. Beitrag und Rhythmus auf alle markierten Mitglieder uebernommen.'
                    : 'Loeschen verschiebt markierte Mitglieder sicher ins Archiv.';
            }
        };

        syncBulkActionState();
        bulkActionSelect.addEventListener('change', syncBulkActionState);

        bulkActionForm.addEventListener('submit', function (event) {
            if (bulkActionSelect.value === 'assign_membership' && bulkMembershipSelect && !bulkMembershipSelect.value) {
                event.preventDefault();
                window.alert('Bitte waehle zuerst eine Mitgliedschaft aus.');
                return;
            }

            if (bulkActionSelect.value !== 'delete') {
                return;
            }

            const confirmed = window.confirm('Die markierten Mitglieder werden ins Archiv verschoben. Wirklich fortfahren?');

            if (!confirmed) {
                event.preventDefault();
            }
        });
    }
</script>
@endpush
@endsection
