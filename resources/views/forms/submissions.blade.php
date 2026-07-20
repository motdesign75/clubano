@extends('layouts.app')

@section('title', 'Formularantworten')

@section('content')
@php
    $canManageForms = auth()->user()?->canManageForms() ?? false;
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Antworten: {{ $form->title }}</h1>
            <p class="text-sm text-gray-500">Alle eingegangenen Antworten dieses Formulars.</p>
        </div>

        @if($canManageForms)
            <a href="{{ route('forms.export', $form) }}"
               class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-emerald-700">
                CSV exportieren
            </a>
        @endif
    </div>

    <div class="space-y-4">
        @forelse($submissions as $submission)
            <div class="rounded-xl bg-white p-6 shadow ring-1 ring-slate-200/70">
                <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-medium text-gray-800">{{ $submission->full_name ?: 'Antwort' }}</div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $submission->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ $submission->status === 'cancelled' ? 'Storniert' : 'Aktiv' }}
                            </span>
                        </div>
                        <div class="mt-1 text-sm text-gray-500">
                            {{ $submission->email ?: 'keine E-Mail' }}{{ $submission->phone ? ' · ' . $submission->phone : '' }}
                        </div>

                        @if($submission->eventBooking)
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">
                                    Buchung {{ $submission->eventBooking->booking_reference }}
                                </span>
                                <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 font-semibold text-indigo-700">
                                    {{ match($submission->eventBooking->booking_status) {
                                        'confirmed' => 'Bestätigt',
                                        'cancelled' => 'Storniert',
                                        default => 'Vorgemerkt',
                                    } }}
                                </span>
                                <span class="inline-flex rounded-full px-2.5 py-1 font-semibold {{ $submission->eventBooking->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($submission->eventBooking->payment_status === 'cancelled' ? 'bg-rose-100 text-rose-700' : ($submission->eventBooking->payment_status === 'not_required' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700')) }}">
                                    {{ match($submission->eventBooking->payment_status) {
                                        'paid' => 'Bezahlt',
                                        'cancelled' => 'Zahlung storniert',
                                        'not_required' => 'Keine Zahlung nötig',
                                        default => 'Zahlung offen',
                                    } }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-3 lg:items-end">
                        <div class="text-sm text-gray-500">
                            {{ $submission->created_at->format('d.m.Y H:i') }}
                            @if($submission->cancelled_at)
                                <div class="mt-1 text-xs text-rose-600">storniert am {{ $submission->cancelled_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </div>

                        @if($canManageForms)
                            <div class="flex flex-col gap-2 sm:flex-row">
                                @if($submission->status !== 'cancelled')
                                    <form method="POST" action="{{ route('forms.submissions.cancel', [$form, $submission]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="inline-flex w-full items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100"
                                                onclick="return confirm('Antwort und ggf. Kursanmeldung wirklich stornieren?');">
                                            Stornieren
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('forms.submissions.destroy', [$form, $submission]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex w-full items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100"
                                            onclick="return confirm('Antwort wirklich löschen? Zugehörige Kurs-Teilnehmer werden ebenfalls entfernt. Verknüpfte Kontakte oder Mitglieder bleiben bestehen.');">
                                        Löschen
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>

                @if($submission->eventBooking && $submission->eventBooking->participants->isNotEmpty())
                    <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-sm font-semibold text-slate-800">Teilnehmer</div>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            @foreach($submission->eventBooking->participants as $participant)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700">
                                    <div class="font-medium text-slate-900">{{ $participant->full_name }}</div>
                                    @if($participant->email || $participant->phone)
                                        <div class="mt-1 text-xs text-slate-500">
                                            {{ $participant->email ?: 'keine E-Mail' }}{{ $participant->phone ? ' · ' . $participant->phone : '' }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <dl class="grid gap-4 md:grid-cols-2">
                    @foreach($submission->answers as $key => $value)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">{{ $key }}</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                @if(is_bool($value))
                                    {{ $value ? 'Ja' : 'Nein' }}
                                @elseif(is_array($value))
                                    {{ implode(', ', $value) }}
                                @elseif(blank($value))
                                    —
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @empty
            <div class="rounded-xl bg-white p-6 text-sm text-gray-500 shadow">
                Noch keine Antworten vorhanden.
            </div>
        @endforelse
    </div>

    {{ $submissions->links() }}
</div>
@endsection
