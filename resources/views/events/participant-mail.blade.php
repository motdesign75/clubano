@extends('layouts.app')

@section('title', 'Teilnehmer anschreiben: '.$event->title)

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Teilnehmermail</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $event->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Schreibe nur die Teilnehmer dieser Veranstaltung an. Der Versand erfolgt erst nach Auswahl und bestätigter Empfängerzahl.
                </p>
            </div>
            <a href="{{ route('events.participants.manage', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                Zur Teilnehmerliste
            </a>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte prüfe die markierten Felder. Es wurde keine Mail versendet.
        </div>
    @endif

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mit E-Mail</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $participants->count() }}</div>
            <div class="mt-1 text-sm text-slate-500">versandfähig</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ohne E-Mail</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $participantsWithoutEmail }}</div>
            <div class="mt-1 text-sm text-slate-500">werden nicht angeschrieben</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Termin</div>
            <div class="mt-2 text-lg font-semibold text-slate-950">{{ optional($event->start)->format('d.m.Y H:i') ?: 'ohne Datum' }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ $event->location ?: 'Ort folgt' }}</div>
        </div>
    </section>

    <form method="POST" action="{{ route('events.participants.mail.send', $event) }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" x-data="{ selected: @js(collect(old('participant_ids', []))->map(fn ($id) => (string) $id)->values()), search: '' }">
        @csrf

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Nachricht</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Was sollen die Teilnehmer wissen?</h2>
            </div>

            <div class="mt-5 space-y-5">
                <div>
                    <label for="subject" class="mb-1 block text-sm font-medium text-slate-700">Betreff</label>
                    <input id="subject" type="text" name="subject" value="{{ old('subject', $defaultSubject) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    @error('subject')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="body" class="mb-1 block text-sm font-medium text-slate-700">Nachricht</label>
                    <textarea id="body" name="body" rows="12" class="w-full rounded-xl border-slate-300 text-sm leading-6 shadow-sm focus:border-slate-500 focus:ring-slate-300">{{ old('body', $defaultBody) }}</textarea>
                    @error('body')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-950">Verfügbare Platzhalter</div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                        <span class="rounded-full bg-white px-3 py-1">{{ '{{ teilnehmer_name }}' }}</span>
                        <span class="rounded-full bg-white px-3 py-1">{{ '{{ event_titel }}' }}</span>
                        <span class="rounded-full bg-white px-3 py-1">{{ '{{ event_datum }}' }}</span>
                        <span class="rounded-full bg-white px-3 py-1">{{ '{{ event_ort }}' }}</span>
                        <span class="rounded-full bg-white px-3 py-1">{{ '{{ buchungsnummer }}' }}</span>
                        <span class="rounded-full bg-white px-3 py-1">{{ '{{ verein_name }}' }}</span>
                    </div>
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Empfänger</div>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950"><span x-text="selected.length"></span> ausgewählt</h2>
                    </div>
                    <button type="button" x-on:click="selected = []" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                        Leeren
                    </button>
                </div>

                <div class="mt-4">
                    <label for="participant_search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Suchen</label>
                    <input id="participant_search" type="search" x-model="search" placeholder="Name, Firma oder E-Mail" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                </div>

                @error('participant_ids')
                    <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                @enderror

                <div class="mt-4 max-h-[32rem] overflow-y-auto rounded-xl border border-slate-200">
                    @forelse($participants as $participant)
                        @php
                            $name = $participant->display_name ?: $participant->full_name ?: $participant->email;
                            $secondary = $participant->email;
                            $searchValue = mb_strtolower(trim($name.' '.$secondary.' '.$participant->organization_name.' '.$participant->booking?->booking_reference));
                        @endphp
                        <label class="flex cursor-pointer items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50"
                               data-search="{{ $searchValue }}"
                               x-show="search === '' || $el.dataset.search.includes(search.toLowerCase())">
                            <input type="checkbox" name="participant_ids[]" value="{{ $participant->id }}" x-model="selected" class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900">{{ $name }}</span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $secondary }}</span>
                                <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                    {{ $participant->booking?->booking_reference ?: 'ohne Buchungsnummer' }}
                                </span>
                            </span>
                        </label>
                    @empty
                        <div class="px-4 py-5 text-sm text-slate-500">Für diese Veranstaltung gibt es noch keine Teilnehmer mit E-Mail-Adresse.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Freigabe</div>
                <h2 class="mt-2 text-xl font-semibold text-amber-950">Erst prüfen, dann senden</h2>
                <p class="mt-2 text-sm leading-6 text-amber-900">
                    Clubano versendet nur an die ausgewählten Teilnehmer dieser Veranstaltung. Bestätige die Zahl, damit kein Verteiler versehentlich zu groß wird.
                </p>

                <div class="mt-4">
                    <label for="recipient_count_confirmation" class="mb-1 block text-sm font-medium text-amber-950">Empfängerzahl bestätigen</label>
                    <input id="recipient_count_confirmation" type="number" min="1" name="recipient_count_confirmation" value="{{ old('recipient_count_confirmation') }}" placeholder="z. B. 12" class="w-full rounded-lg border-amber-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-200">
                    @error('recipient_count_confirmation')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-amber-950">
                    <input type="checkbox" name="send_confirmed" value="1" class="mt-0.5 rounded border-amber-300 text-amber-700 focus:ring-amber-300">
                    <span>Ich habe Empfänger, Betreff und Inhalt geprüft und möchte diese Mail jetzt versenden.</span>
                </label>
                @error('send_confirmed')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-5 inline-flex w-full min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="selected.length === 0">
                    Ausgewählte Teilnehmer anschreiben
                </button>
            </section>
        </aside>
    </form>
</div>
@endsection
