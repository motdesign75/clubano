@extends('layouts.app')

@section('title', 'Spenden-Einstellungen')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <aside class="space-y-2">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Einstellungen</div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Zuwendungsbestätigung</h1>
            <p class="text-sm leading-6 text-slate-500">Diese Angaben werden für die PDF-Bestätigung verwendet. Bitte mit Freistellungsbescheid und Steuerberatung abgleichen.</p>
        </aside>

        <form method="POST" action="{{ route('donations.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Aktivierung</h2>
                <div class="mt-4 rounded-xl border {{ $readiness['can_issue'] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} px-4 py-3 text-sm">
                    <div class="font-semibold">Status: {{ $readiness['label'] }}</div>
                    <p class="mt-1">{{ $readiness['message'] }}</p>
                    @if(!empty($readiness['missing']))
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach($readiness['missing'] as $missing)
                                <li>{{ $missing }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="mt-5 space-y-3">
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <input type="checkbox" name="donation_certificates_enabled" value="1" class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-900" @checked(old('donation_certificates_enabled', $tenant->donation_certificates_enabled))>
                        <span>
                            <span class="block font-medium text-slate-950">Zuwendungsbestätigungen aktivieren</span>
                            <span class="mt-1 block text-slate-500">Der Verein kann Spendenbescheinigungen als PDF erstellen.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <input type="checkbox" name="donation_certificates_send_enabled" value="1" class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-900" @checked(old('donation_certificates_send_enabled', $tenant->donation_certificates_send_enabled))>
                        <span>
                            <span class="block font-medium text-slate-950">Versand vorbereiten</span>
                            <span class="mt-1 block text-slate-500">Aktuell markiert Clubano nur den Versandstatus. Automatischer Mailversand folgt als eigener Schritt.</span>
                        </span>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Gemeinnützigkeit</h2>
                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <label for="freistellung_document" class="block text-sm font-medium text-slate-700">Freistellungsbescheid hochladen</label>
                    <p class="mt-1 text-sm text-slate-500">PDF oder Bilddatei vom Finanzamt. Ohne diesen Nachweis erstellt Clubano keine Zuwendungsbestätigung.</p>
                    <input id="freistellung_document" type="file" name="freistellung_document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-3 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800">
                    @if($tenant->donationFreistellungDocument)
                        <div class="mt-3 text-sm text-slate-600">
                            Aktuell hinterlegt:
                            <a href="{{ route('documents.show', $tenant->donationFreistellungDocument) }}" class="font-medium text-slate-950 underline">
                                {{ $tenant->donationFreistellungDocument->title }}
                            </a>
                            @if($tenant->donationFreistellungDocument->expires_at)
                                <span class="text-slate-500">· gültig bis {{ $tenant->donationFreistellungDocument->expires_at->format('d.m.Y') }}</span>
                            @endif
                        </div>
                    @endif
                    @error('freistellung_document')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="donation_tax_office" class="mb-1 block text-sm font-medium text-slate-600">Finanzamt</label>
                        <input id="donation_tax_office" name="donation_tax_office" value="{{ old('donation_tax_office', $tenant->donation_tax_office) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div>
                        <label for="donation_tax_number" class="mb-1 block text-sm font-medium text-slate-600">Steuernummer</label>
                        <input id="donation_tax_number" name="donation_tax_number" value="{{ old('donation_tax_number', $tenant->donation_tax_number) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div>
                        <label for="donation_notice_authority" class="mb-1 block text-sm font-medium text-slate-600">Bescheid von</label>
                        <input id="donation_notice_authority" name="donation_notice_authority" value="{{ old('donation_notice_authority', $tenant->donation_notice_authority) }}" placeholder="z. B. Finanzamt Hildesheim" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="donation_notice_date" class="mb-1 block text-sm font-medium text-slate-600">Bescheiddatum</label>
                            <input id="donation_notice_date" type="date" name="donation_notice_date" value="{{ old('donation_notice_date', optional($tenant->donation_notice_date)->toDateString()) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        </div>
                        <div>
                            <label for="donation_notice_valid_until" class="mb-1 block text-sm font-medium text-slate-600">Gültig bis</label>
                            <input id="donation_notice_valid_until" type="date" name="donation_notice_valid_until" value="{{ old('donation_notice_valid_until', optional($tenant->donation_notice_valid_until)->toDateString()) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <label for="donation_purposes" class="mb-1 block text-sm font-medium text-slate-600">Begünstigte Zwecke</label>
                    <textarea id="donation_purposes" name="donation_purposes" rows="4" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" placeholder="z. B. Förderung des Sports, der Jugendhilfe oder der Kultur">{{ old('donation_purposes', $tenant->donation_purposes) }}</textarea>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">E-Mail-Text</h2>
                <p class="mt-1 text-sm text-slate-500">Für den späteren Versand. Der Pflichttext der Bescheinigung bleibt davon getrennt.</p>
                <textarea name="donation_email_body" rows="5" class="mt-4 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('donation_email_body', $tenant->donation_email_body ?: "Sehr geehrte Damen und Herren,\n\nvielen Dank für Ihre Spende. Die Zuwendungsbestätigung finden Sie im Anhang.\n\nMit freundlichen Grüßen") }}</textarea>
            </section>

            <div class="flex justify-end gap-3">
                <a href="{{ route('donations.index') }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Zurück</a>
                <button type="submit" class="inline-flex justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Speichern</button>
            </div>
        </form>
    </div>
</div>
@endsection
