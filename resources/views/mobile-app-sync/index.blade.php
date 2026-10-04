@extends('layouts.app')

@section('title', 'App-Synchronisierung')

@section('content')
@php
    $statusClasses = [
        'manual' => 'bg-slate-100 text-slate-700',
        'invited' => 'bg-amber-100 text-amber-800',
        'active' => 'bg-emerald-100 text-emerald-800',
        'failed' => 'bg-rose-100 text-rose-800',
        'ignored' => 'bg-slate-200 text-slate-700',
        'removed' => 'bg-zinc-100 text-zinc-700',
    ];

    $statusLabels = [
        'manual' => 'Manuell',
        'invited' => 'Eingeladen',
        'active' => 'Aktiviert',
        'failed' => 'Fehlgeschlagen',
        'ignored' => 'Ignoriert',
        'removed' => 'Entfernt',
    ];

    $selectedCount = $selectableMembers->where('mobile_app_sync_enabled', true)->count();
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-3xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1fr_auto] lg:items-end lg:px-8">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Kommunikation</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">App-Synchronisierung</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Wähle gezielt aus, welche Mitglieder Zugang zu Mein Clubano erhalten. App-Zugänge bleiben getrennt von der Verwaltungsanwendung.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Ausgewählt</div>
                <div class="mt-2 text-3xl font-semibold">{{ $selectedCount }} / {{ $selectableMembers->count() }}</div>
                <div class="mt-1 text-xs text-slate-400">aktive Mitglieder mit E-Mail</div>
            </div>
        </div>

        <div class="grid border-t border-white/10 sm:grid-cols-2 lg:grid-cols-5">
            @foreach([
                'synced' => ['label' => 'Synchronisiert', 'value' => $stats['synced']],
                'activated' => ['label' => 'Aktiviert', 'value' => $stats['activated']],
                'failed' => ['label' => 'Fehlgeschlagen', 'value' => $stats['failed']],
                'removed' => ['label' => 'Entfernt', 'value' => $stats['removed']],
                'ignored' => ['label' => 'Ignoriert', 'value' => $stats['ignored']],
            ] as $card)
                <div class="border-white/10 px-6 py-4 sm:border-r last:border-r-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $card['label'] }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-medium text-rose-900">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <form method="POST"
              action="{{ route('mobile-app-sync.update') }}"
              class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
              x-data="{ q: '' }">
            @csrf
            @method('PUT')

            <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">Mitglieder auswählen</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                            Nur angehakte Mitglieder werden zur App synchronisiert. Entfernte Häkchen deaktivieren den App-Zugang beim nächsten Synchronisieren, löschen aber keine Mitgliedsdaten.
                        </p>
                    </div>

                    <label class="inline-flex w-fit items-center gap-3 rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-800">
                        <input type="checkbox" name="mobile_app_sync_enabled" value="1" class="rounded border-slate-300" @checked(old('mobile_app_sync_enabled', $tenant->mobile_app_sync_enabled))>
                        Synchronisierung aktiv
                    </label>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <div>
                        <x-ui.label for="mobile_app_sync_tag_id">Zusätzliches Segment</x-ui.label>
                        <select id="mobile_app_sync_tag_id" name="mobile_app_sync_tag_id" class="mt-1 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Keine zusätzliche Eingrenzung</option>
                            @foreach($tags as $tag)
                                <option value="{{ $tag->id }}" @selected((string) old('mobile_app_sync_tag_id', $tenant->mobile_app_sync_tag_id) === (string) $tag->id)>
                                    {{ $tag->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('mobile_app_sync_tag_id')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3">
                        <div class="text-sm font-semibold text-blue-950">{{ $eligibleCount }} Mitglied(er) werden aktuell synchronisiert</div>
                        <p class="mt-1 text-sm leading-6 text-blue-800">
                            Auswahl plus optionales Segment bestimmen gemeinsam, wer eingeladen wird.
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-950">{{ $selectedCount }} von {{ $selectableMembers->count() }} Mitgliedern ausgewählt</div>
                        <div class="mt-1 text-xs text-slate-500">Mitglieder ohne E-Mail-Adresse werden hier nicht angezeigt.</div>
                    </div>
                    <input
                        type="search"
                        x-model.debounce.150ms="q"
                        placeholder="Name, E-Mail oder Nummer suchen..."
                        class="w-full rounded-full border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:w-80"
                    >
                </div>
            </div>

            <div class="max-h-[34rem] divide-y divide-slate-100 overflow-y-auto">
                @forelse($selectableMembers as $member)
                    @php
                        $searchText = mb_strtolower(trim($member->full_name . ' ' . $member->email . ' ' . $member->member_id));
                        $appUser = $member->mobileAppUser;
                    @endphp
                    <label
                        class="grid cursor-pointer gap-3 px-5 py-4 transition hover:bg-slate-50 sm:px-6 lg:grid-cols-[auto_minmax(0,1fr)_auto]"
                        x-show="@js($searchText).includes(q.toLowerCase())"
                    >
                        <input
                            type="checkbox"
                            name="mobile_app_member_ids[]"
                            value="{{ $member->id }}"
                            class="mt-1 rounded border-slate-300"
                            @checked(old('mobile_app_member_ids') ? in_array((string) $member->id, old('mobile_app_member_ids', []), true) : $member->mobile_app_sync_enabled)
                        >
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-slate-900">{{ $member->full_name ?: 'Ohne Namen' }}</div>
                            <div class="mt-1 truncate text-xs text-slate-500">
                                {{ $member->email }}
                                @if($member->member_id)
                                    <span class="text-slate-300">·</span> Nr. {{ $member->member_id }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 lg:justify-end">
                            @if($appUser)
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$appUser->sync_status] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ $statusLabels[$appUser->sync_status] ?? $appUser->sync_status }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    Noch nicht synchronisiert
                                </span>
                            @endif
                        </div>
                    </label>
                @empty
                    <div class="px-6 py-12 text-center">
                        <h3 class="text-lg font-semibold text-slate-950">Keine auswählbaren Mitglieder</h3>
                        <p class="mt-2 text-sm text-slate-500">Lege zuerst eine E-Mail-Adresse bei Mitgliedern an.</p>
                    </div>
                @endforelse
            </div>

            <div class="border-t border-slate-200 bg-white px-5 py-4 sm:px-6">
                <button class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Auswahl speichern
                </button>
            </div>
        </form>

        <aside class="space-y-6">
            <form method="POST" action="{{ route('mobile-app-sync.run') }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-xl font-semibold text-slate-950">Synchronisieren</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Neue App-Zugänge erhalten eine Einladung per E-Mail. Bestehende aktivierte Zugänge bleiben erhalten.
                </p>

                <label class="mt-6 inline-flex items-center gap-3 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="send_invitations" value="1" class="rounded border-slate-300" checked>
                    Einladungen direkt versenden
                </label>

                <button class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300" @disabled(! $tenant->mobile_app_sync_enabled)>
                    Jetzt synchronisieren
                </button>

                @unless($tenant->mobile_app_sync_enabled)
                    <p class="mt-3 text-sm font-medium text-amber-700">Aktiviere die Synchronisierung und speichere zuerst.</p>
                @endunless
            </form>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-950">Letzte App-Zugänge</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($appUsers->take(8) as $appUser)
                        <div class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-slate-900">{{ $appUser->member?->full_name ?: 'Ohne Mitglied' }}</div>
                                    <div class="mt-1 truncate text-xs text-slate-500">{{ $appUser->username }}</div>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$appUser->sync_status] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ $statusLabels[$appUser->sync_status] ?? $appUser->sync_status }}
                                </span>
                            </div>
                            @if($appUser->sync_error)
                                <div class="mt-2 text-xs text-rose-700">{{ $appUser->sync_error }}</div>
                            @elseif($appUser->accepted_at)
                                <div class="mt-2 text-xs text-slate-500">Aktiviert am {{ $appUser->accepted_at->format('d.m.Y H:i') }}</div>
                            @elseif($appUser->invitation_sent_at)
                                <div class="mt-2 text-xs text-slate-500">Einladung am {{ $appUser->invitation_sent_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500">Noch keine App-Zugänge vorhanden.</div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
