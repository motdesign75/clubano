@extends('layouts.app')

@section('title', 'Mitglieder einladen')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_52%,#1f2937_100%)] p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr),320px]">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Verein verwalten</div>
                <h1 class="mt-5 text-3xl font-semibold tracking-tight text-white">Mitglieder einladen</h1>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-white/68">
                    Wähle Mitglieder aus, setze eine Rolle und Clubano verschickt einen Link, mit dem die Person ihr eigenes Passwort festlegt.
                </p>
            </div>

            <aside class="rounded-xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Einladbar</div>
                <div class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $members->count() }}</div>
                <p class="mt-2 text-sm leading-6 text-white/60">
                    {{ ($unavailableMembers ?? collect())->count() }} brauchen vorher Aufmerksamkeit.
                </p>
                <a href="{{ route('users.index') }}" class="mt-5 inline-flex min-h-10 items-center justify-center rounded-xl border border-white/18 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">
                    Zurück zu Benutzern
                </a>
            </aside>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">Bitte prüfe deine Auswahl.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('users.invite-members.store') }}" class="space-y-6" x-data="{ query: '', selected: [] }">
        @csrf

        <section class="grid gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <label for="role" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Rolle</label>
                <select id="role" name="role" required class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    @foreach($roleOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>

                <div class="mt-5 space-y-3">
                    @foreach($roleOptions as $option)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="text-sm font-semibold text-slate-900">{{ $option['label'] }}</div>
                            <p class="mt-1 text-xs leading-5 text-slate-600">{{ $option['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </aside>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Mitglieder auswählen</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $members->count() }} einladbar
                                @if(($unavailableMembers ?? collect())->isNotEmpty())
                                    · {{ $unavailableMembers->count() }} brauchen vorher Aufmerksamkeit
                                @endif
                            </p>
                        </div>
                        <div class="w-full md:max-w-sm">
                            <label for="member-search" class="sr-only">Mitglieder suchen</label>
                            <input id="member-search" x-model.debounce.150ms="query" type="search"
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                                   placeholder="Name oder E-Mail suchen">
                        </div>
                    </div>
                </div>

                @if($members->isEmpty())
                    <div class="p-8 text-center">
                        <div class="text-base font-semibold text-slate-900">Keine passenden Mitglieder gefunden.</div>
                        <p class="mt-2 text-sm text-slate-500">Bei den aktiven Mitgliedern fehlen E-Mail-Adressen oder es bestehen bereits Benutzerzugänge.</p>
                    </div>
                @else
                    <div class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto">
                        @foreach($members as $member)
                            @php
                                $displayName = trim($member->full_name) ?: ($member->organization ?: $member->email);
                                $searchText = mb_strtolower($displayName . ' ' . $member->email . ' ' . $member->organization);
                            @endphp

                            <label class="flex cursor-pointer items-start gap-4 px-5 py-4 hover:bg-slate-50"
                                   x-show="'{{ e($searchText) }}'.includes(query.toLowerCase())">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" x-model="selected"
                                       class="mt-1 rounded border-slate-300 text-slate-950 shadow-sm focus:ring-slate-400">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-slate-950">{{ $displayName }}</span>
                                    <span class="mt-1 block break-all text-sm text-slate-500">{{ $member->email }}</span>
                                    @if($member->organization)
                                        <span class="mt-1 block text-xs text-slate-400">{{ $member->organization }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-slate-500">
                            <span x-text="selected.length"></span> ausgewählt
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                            Einladungen senden
                        </button>
                    </div>
                @endif
            </section>
        </section>
    </form>

    @if(($unavailableMembers ?? collect())->isNotEmpty())
        <section class="rounded-2xl border border-amber-200 bg-amber-50/70 shadow-sm">
            <div class="border-b border-amber-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-amber-950">Noch nicht einladbar</h2>
                <p class="mt-1 text-sm text-amber-800">
                    Diese aktiven Mitglieder sind sichtbar, können aber erst eingeladen werden, wenn der Grund behoben ist.
                </p>
            </div>

            <div class="max-h-[360px] divide-y divide-amber-100 overflow-y-auto bg-white/60">
                @foreach($unavailableMembers as $member)
                    @php
                        $displayName = trim($member->full_name) ?: ($member->organization ?: 'Unbenanntes Mitglied');
                    @endphp

                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-950">{{ $displayName }}</div>
                            <div class="mt-1 break-all text-sm text-slate-500">{{ $member->email ?: 'Keine E-Mail hinterlegt' }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                {{ $member->invite_blocked_reason }}
                            </span>
                            @if(blank($member->email))
                                <a href="{{ route('members.edit', $member) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-amber-300 bg-white px-3 text-xs font-semibold text-amber-900 hover:bg-amber-50">
                                    E-Mail ergänzen
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
