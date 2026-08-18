@extends('layouts.app')

@section('title', 'Gutschein anlegen')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Gutschein</div>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Gutschein anlegen</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
            Für neue Gutscheine erzeugt Clubano automatisch einen Code. Alte Papiergutscheine kannst du als Altbestand erfassen.
        </p>
    </section>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte prüfe die markierten Felder.
        </div>
    @endif

    <form method="POST" action="{{ route('vouchers.store') }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @csrf

        <div class="grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Bezeichnung</label>
                <input id="title" type="text" name="title" value="{{ old('title', 'Kursgutschein') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('title')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="original_amount" class="mb-1 block text-sm font-medium text-slate-700">Wert</label>
                <input id="original_amount" type="number" step="0.01" min="0.01" name="original_amount" value="{{ old('original_amount', number_format($defaultAmount, 2, '.', '')) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('original_amount')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="code" class="mb-1 block text-sm font-medium text-slate-700">Code optional</label>
                <input id="code" type="text" name="code" value="{{ old('code') }}" placeholder="leer lassen für automatische Nummer" class="w-full rounded-xl border-slate-300 text-sm uppercase shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('code')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="issued_at" class="mb-1 block text-sm font-medium text-slate-700">Ausgabedatum</label>
                <input id="issued_at" type="date" name="issued_at" value="{{ old('issued_at', $defaultIssuedAt) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('issued_at')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="expires_at" class="mb-1 block text-sm font-medium text-slate-700">Gültig bis optional</label>
                <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('expires_at')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="buyer_name" class="mb-1 block text-sm font-medium text-slate-700">Käufer</label>
                <input id="buyer_name" type="text" name="buyer_name" value="{{ old('buyer_name') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('buyer_name')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="buyer_email" class="mb-1 block text-sm font-medium text-slate-700">E-Mail Käufer</label>
                <input id="buyer_email" type="email" name="buyer_email" value="{{ old('buyer_email') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('buyer_email')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="recipient_name" class="mb-1 block text-sm font-medium text-slate-700">Empfänger</label>
                <input id="recipient_name" type="text" name="recipient_name" value="{{ old('recipient_name') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('recipient_name')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="recipient_email" class="mb-1 block text-sm font-medium text-slate-700">E-Mail Empfänger</label>
                <input id="recipient_email" type="email" name="recipient_email" value="{{ old('recipient_email') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                @error('recipient_email')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <label for="delivery_method" class="mb-1 block text-sm font-medium text-slate-700">Zustellung</label>
                <select id="delivery_method" name="delivery_method" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="pickup" @selected(old('delivery_method', 'pickup') === 'pickup')>Abholung</option>
                    <option value="mail" @selected(old('delivery_method') === 'mail')>Per E-Mail versenden</option>
                    <option value="internal" @selected(old('delivery_method') === 'internal')>Nur intern erfassen</option>
                </select>
                <p class="mt-1 text-sm text-slate-500">Der Gutschein wird gespeichert. Den PDF-Versand startest du anschließend bewusst aus der Gutscheinliste.</p>
                @error('delivery_method')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <input type="checkbox" name="legacy" value="1" @checked(old('legacy')) class="mt-0.5 rounded border-amber-300 text-amber-700 focus:ring-amber-300">
                    <span>
                        <span class="block font-semibold">Altbestand ohne bisherige Nummer</span>
                        <span class="mt-1 block text-amber-800">Für bereits ausgegebene Papiergutscheine. Clubano erzeugt ab jetzt einen nachvollziehbaren Code.</span>
                    </span>
                </label>
            </div>

            <div class="md:col-span-2">
                <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notiz</label>
                <textarea id="notes" name="notes" rows="4" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">{{ old('notes') }}</textarea>
                @error('notes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('vouchers.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Abbrechen
            </a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Gutschein speichern
            </button>
        </div>
    </form>
</div>
@endsection
