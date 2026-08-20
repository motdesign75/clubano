@extends('layouts.app')

@section('title', 'Neue Mitgliedschaft')

@section('content')
    @php
        $intervals = [
            'monatlich' => ['label' => 'Monatlich', 'hint' => '12 Rechnungsperioden pro Jahr'],
            'vierteljaehrlich' => ['label' => 'Vierteljaehrlich', 'hint' => 'Q1 bis Q4'],
            'halbjaehrlich' => ['label' => 'Halbjaehrlich', 'hint' => 'H1 und H2'],
            'jaehrlich' => ['label' => 'Jaehrlich', 'hint' => 'eine Jahresrechnung'],
        ];
        $selectedInterval = match (old('interval', 'jaehrlich')) {
            'vierteljährlich' => 'vierteljaehrlich',
            'halbjährlich' => 'halbjaehrlich',
            'jährlich' => 'jaehrlich',
            default => old('interval', 'jaehrlich'),
        };
    @endphp

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Beitragsmodell</div>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Neue Mitgliedschaft</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    Definiere den Beitrag so klar, dass spätere Rechnungen ohne Nachdenken entstehen können.
                </p>
            </div>

            <a href="{{ route('memberships.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Zurück
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
                Bitte prüfe die markierten Felder.
            </div>
        @endif

        <form method="POST" action="{{ route('memberships.store') }}" class="grid gap-6 lg:grid-cols-[1fr,0.7fr]">
            @csrf

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-800">Bezeichnung</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-2 block w-full rounded-2xl border-slate-300 text-lg font-medium shadow-sm focus:border-[#2954A3] focus:ring-[#2954A3]" placeholder="z. B. Erwachsene">
                        <p class="mt-2 text-sm text-slate-500">So erscheint die Mitgliedschaft später in Mitgliedern, Rechnungen und Filtern.</p>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-semibold text-slate-800">Beitrag pro Intervall</label>
                        <div class="relative mt-1 rounded-md shadow-sm">
                            <input type="text" name="amount" id="amount" inputmode="decimal" value="{{ old('amount') }}" required class="block w-full rounded-2xl border-slate-300 pr-16 text-lg font-semibold shadow-sm focus:border-[#2954A3] focus:ring-[#2954A3]" placeholder="72,00">
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-slate-500">Euro</div>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">Kommawerte sind erlaubt, zum Beispiel 12,50.</p>
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <label for="admission_fee" class="block text-sm font-semibold text-slate-800">Einmalige Aufnahmegebühr</label>
                        <div class="relative mt-1 rounded-md shadow-sm">
                            <input type="text" name="admission_fee" id="admission_fee" inputmode="decimal" value="{{ old('admission_fee') }}" class="block w-full rounded-2xl border-slate-300 pr-16 text-lg font-semibold shadow-sm focus:border-[#2954A3] focus:ring-[#2954A3]" placeholder="0,00">
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-slate-500">Euro</div>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">Optional. Wird bei der ersten Beitragsrechnung eines neuen Mitglieds einmalig ergänzt.</p>
                        <x-input-error :messages="$errors->get('admission_fee')" class="mt-2" />
                    </div>

                    <div>
                        <div class="text-sm font-semibold text-slate-800">Abrechnungsrhythmus</div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach($intervals as $value => $option)
                                @php($inputId = 'interval-create-' . $value)
                                <div>
                                    <input type="radio" id="{{ $inputId }}" name="interval" value="{{ $value }}" class="peer sr-only" @checked($selectedInterval === $value)>
                                    <label for="{{ $inputId }}" class="flex cursor-pointer rounded-2xl border px-4 py-4 transition peer-checked:border-[#2954A3] peer-checked:bg-blue-50 peer-checked:ring-1 peer-checked:ring-[#2954A3] border-slate-200 bg-white hover:border-slate-300">
                                        <span>
                                            <span class="block font-semibold text-slate-950">{{ $option['label'] }}</span>
                                            <span class="mt-1 block text-sm text-slate-500">{{ $option['hint'] }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('interval')" class="mt-2" />
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-3xl bg-slate-950 p-6 text-white shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/50">Warum sauber anlegen?</div>
                    <h2 class="mt-4 text-2xl font-semibold">Ein Modell. Viele saubere Rechnungen.</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-300">
                        Beim Zuordnen zu einem Mitglied speichert Clubano Beitrag und Rhythmus als Snapshot. Die Aufnahmegebühr bleibt am Modell und wird nur einmalig berechnet.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-[#2954A3] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1E3F7F]">
                        Beitragsmodell speichern
                    </button>
                </div>
            </aside>
        </form>
    </div>
@endsection
