@extends('layouts.app')

@section('title', 'Namensschilder')

@section('content')
@php
    $selectedLookup = collect($selectedMemberIds ?? [])->mapWithKeys(fn ($id) => [(string) $id => true]);
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-[28px] bg-slate-950 px-6 py-5 text-white shadow-sm sm:px-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Mitglieder</div>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Namensschilder drucken</h1>
                <p class="mt-2 max-w-lg text-sm leading-6 text-slate-300">
                    Wähle die Mitglieder aus, für die Clubano Namensschilder im Visitenkartenformat quer vorbereitet.
                </p>
            </div>

            <a href="{{ route('members.index') }}"
               class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">
                Zur Mitgliederliste
            </a>
        </div>
    </section>

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900">
            {{ session('error') }}
        </div>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('members.name-badges') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <div>
                <label for="member-badge-search" class="mb-1 block text-sm font-medium text-slate-700">Mitglieder suchen</label>
                <input id="member-badge-search"
                       type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Name, Mitgliedsnummer, Organisation oder E-Mail"
                       class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-full bg-slate-950 px-5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    Suchen
                </button>
                <a href="{{ route('members.name-badges') }}" class="inline-flex min-h-11 items-center justify-center rounded-full border border-slate-200 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Zurücksetzen
                </a>
            </div>
        </form>
    </section>

    <form method="POST" action="{{ route('members.name-badges.print') }}" target="_blank" class="space-y-5" id="name-badge-print-form">
        @csrf

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-950">Auswahl</div>
                    <div class="mt-1 text-sm text-slate-500">
                        {{ $members->count() }} Mitglied(er) in dieser Ansicht. Markiere die Namen, die gedruckt werden sollen.
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="button"
                            data-select-all-badges
                            class="inline-flex min-h-10 items-center justify-center rounded-full border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Alle auswählen
                    </button>
                    <button type="button"
                            data-clear-badges
                            class="inline-flex min-h-10 items-center justify-center rounded-full border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Auswahl leeren
                    </button>
                    <button type="submit"
                            class="inline-flex min-h-10 items-center justify-center rounded-full bg-slate-950 px-5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Namensschilder drucken
                    </button>
                </div>
            </div>

            @if($members->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-slate-500">
                    Keine Mitglieder gefunden.
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($members as $member)
                        @php
                            $name = trim(implode(' ', array_filter([$member->first_name, $member->last_name])));
                            $name = $name !== '' ? $name : ($member->full_name ?: ($member->organization ?: 'Ohne Namen'));
                        @endphp
                        <label class="flex cursor-pointer items-start gap-4 px-5 py-4 transition hover:bg-slate-50">
                            <input type="checkbox"
                                   name="member_ids[]"
                                   value="{{ $member->id }}"
                                   class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-800"
                                   @checked(isset($selectedLookup[(string) $member->id]))>
                            <span class="min-w-0 flex-1">
                                <span class="block break-words text-base font-semibold text-slate-950">{{ $name }}</span>
                                <span class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                                    @if($member->member_id)
                                        <span>Nr. {{ $member->member_id }}</span>
                                    @endif
                                    @if($member->organization)
                                        <span>{{ $member->organization }}</span>
                                    @endif
                                    @if($member->email)
                                        <span>{{ $member->email }}</span>
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>
    </form>
</div>

@push('scripts')
<script>
    const badgeForm = document.getElementById('name-badge-print-form');
    const badgeCheckboxes = () => Array.from(document.querySelectorAll('input[name="member_ids[]"]'));

    document.querySelector('[data-select-all-badges]')?.addEventListener('click', () => {
        badgeCheckboxes().forEach((checkbox) => {
            checkbox.checked = true;
        });
    });

    document.querySelector('[data-clear-badges]')?.addEventListener('click', () => {
        badgeCheckboxes().forEach((checkbox) => {
            checkbox.checked = false;
        });
    });

    badgeForm?.addEventListener('submit', (event) => {
        if (! badgeCheckboxes().some((checkbox) => checkbox.checked)) {
            event.preventDefault();
            window.alert('Bitte wähle mindestens ein Mitglied aus.');
        }
    });
</script>
@endpush
@endsection
