@extends('layouts.app')

@section('title', 'Neues Konto anlegen')

@section('content')
{{-- Fix für Mobile-/Tablet-Querformat:
     - eigener Scroll-Container mit max-h:[100dvh]
     - sticky Buttonleiste am unteren Rand
     - sichere Abstände für iOS-Safe-Area --}}
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">➕ Neues Konto</h1>
        <a href="{{ route('accounts.index') }}"
           class="hidden sm:inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Zur Übersicht
        </a>
    </div>

    <!-- Karte mit eigenem Scrollbereich (funktioniert auch bei Eltern mit h-screen) -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm flex flex-col"
         style="max-height: 100dvh">

        <!-- Inhalt: scrollt unabhängig vom Fenster -->
        <form method="POST" action="{{ route('accounts.store') }}"
              class="grow overflow-y-auto p-4 sm:p-6"
              aria-label="Konto anlegen">
            @csrf

            <!-- Responsive 2-Spalten-Grid ab md -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">

                {{-- Kontoname (wichtigste Felder zuerst) --}}
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-slate-700">
                        Kontoname <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm
                               focus:border-indigo-400 focus:ring-indigo-400 @error('name') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Typ --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-slate-700">Kontotyp <span class="text-red-500">*</span></label>
                    <select id="type" name="type" required
                            class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm
                                   focus:border-indigo-400 focus:ring-indigo-400 @error('type') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">
                        <option value="">Bitte wählen</option>
                        <option value="bank"     {{ old('type') === 'bank' ? 'selected' : '' }}>Bankkonto</option>
                        <option value="kasse"    {{ old('type') === 'kasse' ? 'selected' : '' }}>Kasse</option>
                        <option value="einnahme" {{ old('type') === 'einnahme' ? 'selected' : '' }}>Einnahme</option>
                        <option value="ausgabe"  {{ old('type') === 'ausgabe' ? 'selected' : '' }}>Ausgabe</option>
                    </select>
                    @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Steuerlicher Bereich --}}
                <div>
                    <label for="tax_area" class="block text-sm font-medium text-slate-700">Steuerlicher Bereich</label>
                    <select id="tax_area" name="tax_area"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm
                                   focus:border-indigo-400 focus:ring-indigo-400 @error('tax_area') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">
                        <option value="">Bitte wählen</option>
                        <option value="ideell"         {{ old('tax_area') === 'ideell' ? 'selected' : '' }}>Ideeller Bereich</option>
                        <option value="zweckbetrieb"   {{ old('tax_area') === 'zweckbetrieb' ? 'selected' : '' }}>Zweckbetrieb</option>
                        <option value="vermoegensverwaltung" {{ old('tax_area') === 'vermoegensverwaltung' ? 'selected' : '' }}>Vermögensverwaltung</option>
                        <option value="wirtschaftlich" {{ old('tax_area') === 'wirtschaftlich' ? 'selected' : '' }}>Wirtschaftlicher Geschäftsbetrieb</option>
                    </select>
                    @error('tax_area') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Kontonummer --}}
                <div>
                    <label for="number" class="block text-sm font-medium text-slate-700">Kontonummer</label>
                    <input type="text" id="number" name="number" value="{{ old('number') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm
                                  focus:border-indigo-400 focus:ring-indigo-400 @error('number') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                           placeholder="z. B. 1000 oder leer lassen">
                    @error('number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- IBAN --}}
                <div>
                    <label for="iban" class="block text-sm font-medium text-slate-700">IBAN</label>
                    <input type="text" id="iban" name="iban" value="{{ old('iban') }}"
                           inputmode="text" autocapitalize="characters"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-base tracking-wider uppercase shadow-sm
                                  focus:border-indigo-400 focus:ring-indigo-400 @error('iban') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                           placeholder="optional">
                    @error('iban') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- BIC --}}
                <div>
                    <label for="bic" class="block text-sm font-medium text-slate-700">BIC</label>
                    <input type="text" id="bic" name="bic" value="{{ old('bic') }}"
                           inputmode="text" autocapitalize="characters"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-base tracking-wider uppercase shadow-sm
                                  focus:border-indigo-400 focus:ring-indigo-400 @error('bic') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                           placeholder="optional">
                    @error('bic') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Beschreibung (über 2 Spalten) --}}
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-slate-700">Beschreibung</label>
                    <textarea id="description" name="description" rows="3"
                              class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm
                                     focus:border-indigo-400 focus:ring-indigo-400 @error('description') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Anfangsbestand --}}
                <div>
                    <label for="balance_start" class="block text-sm font-medium text-slate-700">Anfangsbestand (€)</label>
                    <input type="number" step="0.01" inputmode="decimal" id="balance_start" name="balance_start" value="{{ old('balance_start') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm
                                  focus:border-indigo-400 focus:ring-indigo-400 @error('balance_start') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                           placeholder="z. B. 100.00">
                    @error('balance_start') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Datum Anfangsbestand --}}
                <div>
                    <label for="balance_date" class="block text-sm font-medium text-slate-700">Datum Anfangsbestand</label>
                    <input type="date" id="balance_date" name="balance_date" value="{{ old('balance_date') }}"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-base shadow-sm
                                  focus:border-indigo-400 focus:ring-indigo-400 @error('balance_date') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">
                    @error('balance_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Schalter (wrappt sauber auf kleinen Höhen) --}}
                <div class="md:col-span-2">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-center">
                        <input type="hidden" name="active" value="0">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="active" class="rounded text-indigo-600 focus:ring-indigo-400" value="1" {{ old('active', true) ? 'checked' : '' }}>
                            <span class="text-sm text-slate-700">Aktiv</span>
                        </label>

                        <input type="hidden" name="online" value="0">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="online" class="rounded text-indigo-600 focus:ring-indigo-400" value="1" {{ old('online') ? 'checked' : '' }}>
                            <span class="text-sm text-slate-700">Online abrufbar</span>
                        </label>
                    </div>
                </div>
            </div>
        </form>

        <!-- Sticky Buttonleiste: bleibt im Blick, auch wenn gescrollt wird -->
        <div class="sticky bottom-0 inset-x-0 border-t border-slate-200 bg-white/90 backdrop-blur px-4 sm:px-6 py-3"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom))">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('accounts.index') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                    Abbrechen
                </a>
                <button form="__none" type="submit"
                        onclick="this.closest('div').previousElementSibling.requestSubmit()"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2">
                    💾 Speichern
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
