@extends('layouts.app')

@section('title', 'Stammdaten prüfen')

@section('content')
@php
    $statusLabels = [
        'sent' => 'Versendet',
        'submitted' => 'Zur Prüfung',
        'approved' => 'Übernommen',
        'rejected' => 'Abgelehnt',
    ];

    $statusClasses = [
        'sent' => 'bg-sky-50 text-sky-700 border-sky-200',
        'submitted' => 'bg-amber-50 text-amber-800 border-amber-200',
        'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-slate-100 text-slate-700 border-slate-200',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Stammdaten</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Daten prüfen lassen</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Schicke ausgewählten Mitgliedern oder Kontakten einen sicheren Link. Eingereichte Änderungen landen hier zur Prüfung und werden erst nach Freigabe übernommen.
                </p>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="grid gap-6 lg:grid-cols-[0.95fr_1.4fr]">
        <div class="space-y-6">
            <form method="POST" action="{{ route('data-update-requests.store') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf

                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Anfrage versenden</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">
                        Wähle aus, wer die eigenen gespeicherten Daten prüfen und ergänzen soll.
                    </p>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">
                        <input type="radio" name="target_type" value="member" class="text-indigo-600" checked>
                        Mitglieder
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">
                        <input type="radio" name="target_type" value="contact" class="text-indigo-600">
                        Kontakte
                    </label>
                </div>

                <div class="mt-5">
                    <label for="message" class="text-sm font-semibold text-slate-800">Hinweis in der E-Mail</label>
                    <textarea id="message" name="message" rows="3" class="mt-2 block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optionaler Hinweis, warum die Daten geprüft werden sollen.">{{ old('message') }}</textarea>
                </div>

                <div class="mt-5 grid gap-5">
                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-800">Mitglieder</div>
                        <div class="max-h-72 overflow-auto rounded-2xl border border-slate-200">
                            @forelse($members as $member)
                                <label class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                                    <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="mt-1 rounded border-slate-300 text-indigo-600">
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-slate-900">{{ $member->full_name ?: $member->organization ?: 'Ohne Namen' }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $member->email ?: 'Keine E-Mail hinterlegt' }}</span>
                                    </span>
                                </label>
                            @empty
                                <div class="px-4 py-5 text-sm text-slate-500">Keine Mitglieder vorhanden.</div>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-800">Kontakte</div>
                        <div class="max-h-72 overflow-auto rounded-2xl border border-slate-200">
                            @forelse($contacts as $contact)
                                <label class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0">
                                    <input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" class="mt-1 rounded border-slate-300 text-indigo-600">
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-slate-900">{{ $contact->display_name }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $contact->primary_email ?: 'Keine E-Mail hinterlegt' }}</span>
                                    </span>
                                </label>
                            @empty
                                <div class="px-4 py-5 text-sm text-slate-500">Keine Kontakte vorhanden.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <button type="submit" class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    Anfrage per E-Mail senden
                </button>
            </form>
        </div>

        <div class="space-y-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                    <h2 class="text-lg font-semibold text-slate-950">Eingegangene Rückmeldungen</h2>
                        <p class="mt-1 text-sm text-slate-500">Die neuesten 100 Anfragen und Antworten.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $requests->count() }}</span>
                </div>
            </div>

            @forelse($requests as $dataRequest)
                @php
                    $recipient = $dataRequest->recipient();
                    $changes = $dataRequest->changes ?? [];
                @endphp

                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-slate-950">{{ $dataRequest->recipient_name ?: 'Ohne Namen' }}</h3>
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClasses[$dataRequest->status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                    {{ $statusLabels[$dataRequest->status] ?? $dataRequest->status }}
                                </span>
                            </div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ $dataRequest->recipient_email }} · {{ $dataRequest->member_id ? 'Mitglied' : 'Kontakt' }}
                            </div>
                            <div class="mt-1 text-xs text-slate-400">
                                Versandt {{ optional($dataRequest->sent_at)->format('d.m.Y H:i') ?: '-' }}
                                @if($dataRequest->submitted_at)
                                    · Antwort {{ $dataRequest->submitted_at->format('d.m.Y H:i') }}
                                @endif
                            </div>
                        </div>

                        @if($recipient)
                            <a href="{{ $dataRequest->member_id ? route('members.show', $recipient) : route('contacts.show', $recipient) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                                Datensatz öffnen
                            </a>
                        @endif
                    </div>

                    @if($dataRequest->status === 'submitted')
                        @if($dataRequest->message)
                            <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                {{ $dataRequest->message }}
                            </div>
                        @endif

                        @if(count($changes) > 0)
                            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Feld</th>
                                            <th class="px-4 py-3">Bisher</th>
                                            <th class="px-4 py-3">Neu</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach($changes as $change)
                                            <tr>
                                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $change['label'] }}</td>
                                                <td class="px-4 py-3 text-slate-500">{{ $change['old'] ?: 'leer' }}</td>
                                                <td class="px-4 py-3 text-slate-900">{{ $change['new'] ?: 'leer' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                Es wurden keine Änderungen eingereicht. Die gespeicherten Daten wurden bestätigt.
                            </div>
                        @endif

                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <form method="POST" action="{{ route('data-update-requests.approve', $dataRequest) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                    Übernehmen
                                </button>
                            </form>
                            <form method="POST" action="{{ route('data-update-requests.reject', $dataRequest) }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-full border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Ablehnen
                                </button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Noch keine Stammdatenprüfungen</h2>
                    <p class="mt-2 text-sm text-slate-500">Sobald eine Anfrage versendet oder beantwortet wurde, erscheint sie hier.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
