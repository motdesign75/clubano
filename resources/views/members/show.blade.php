@extends('layouts.app')

@section('title', 'Mitglied: ' . $member->full_name)

@section('content')
@php
    $currentUser = auth()->user();
    $canManageMembers = $currentUser?->canManageMembers() ?? false;
    $canManageFinance = $currentUser?->canManageFinance() ?? false;

    $statusTone = match($member->status) {
        'aktiv' => 'bg-emerald-100 text-emerald-700',
        'ehemalig' => 'bg-slate-100 text-slate-700',
        'zukünftig' => 'bg-blue-100 text-blue-700',
        'archiviert' => 'bg-amber-100 text-amber-700',
        default => 'bg-rose-100 text-rose-700',
    };

    $quickFacts = [
        ['label' => 'Mitgliedsnummer', 'value' => $member->member_id ?: 'Noch nicht vergeben'],
        ['label' => 'Eintritt', 'value' => $member->entry_date ? $member->entry_date->format('d.m.Y') : 'Noch offen'],
        ['label' => 'Austritt', 'value' => $member->exit_date ? $member->exit_date->format('d.m.Y') : 'Aktuell kein Austritt'],
        ['label' => 'Kündigung', 'value' => $member->termination_date ? $member->termination_date->format('d.m.Y') : 'Nicht hinterlegt'],
    ];

    $profileDetails = [
        ['label' => 'Geburtstag', 'value' => $member->birthday ? $member->birthday->format('d.m.Y') : 'Nicht hinterlegt'],
        ['label' => 'Organisation', 'value' => $member->organization ?: 'Keine Angabe'],
        ['label' => 'Anrede', 'value' => $member->salutation ?: 'Keine Angabe'],
        ['label' => 'Titel', 'value' => $member->title ?: 'Keine Angabe'],
    ];

    $contactDetails = [
        ['label' => 'E-Mail', 'value' => $member->email ?: 'Keine E-Mail', 'link' => $member->email ? 'mailto:' . $member->email : null],
        ['label' => 'Mobil', 'value' => $member->mobile ?: 'Keine Mobilnummer', 'link' => $member->mobile ? 'tel:' . $member->mobile : null],
        ['label' => 'Festnetz', 'value' => $member->landline ?: 'Kein Festnetz', 'link' => $member->landline ? 'tel:' . $member->landline : null],
        ['label' => 'WhatsApp', 'value' => $member->whatsapp_phone ?: 'Keine Nummer'],
        ['label' => 'Bevorzugter Kanal', 'value' => match($member->preferred_contact_channel) {
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'whatsapp' => 'WhatsApp',
            'post' => 'Post',
            'none' => 'Keine Kontaktaufnahme',
            default => 'Nicht hinterlegt',
        }],
        ['label' => 'Zuletzt kontaktiert', 'value' => $member->last_contacted_at ? $member->last_contacted_at->format('d.m.Y H:i') : 'Noch nie'],
    ];

    $addressLines = array_values(array_filter([
        $member->street,
        $member->address_addition,
        trim(($member->zip ?: '') . (($member->zip && $member->city) ? ' ' : '') . ($member->city ?: '')),
        $member->country ? (config('countries.list')[$member->country] ?? $member->country) : null,
        $member->care_of ? 'C/O ' . $member->care_of : null,
    ]));

    $paymentDetails = [
        ['label' => 'Zahlungsart', 'value' => $member->paymentMethodLabel()],
        ['label' => 'IBAN', 'value' => $member->iban ?: 'Nicht hinterlegt'],
        ['label' => 'BIC', 'value' => $member->bic ?: 'Nicht hinterlegt'],
        ['label' => 'Mandatsreferenz', 'value' => $member->sepa_mandate_reference ?: 'Nicht hinterlegt'],
        ['label' => 'SEPA unterschrieben', 'value' => $member->sepa_signed_at ? $member->sepa_signed_at->format('d.m.Y') : 'Nicht hinterlegt'],
        ['label' => 'Kontoinhaber', 'value' => $member->sepa_account_holder ?: 'Entspricht Mitglied'],
    ];

    $privacyBadges = [
        'Datenverarbeitung' => $member->consent_data_processing,
        'Foto intern' => $member->consent_photo_internal,
        'Foto öffentlich' => $member->consent_photo_public,
    ];

    $contactPermissions = [
        'E-Mail' => $member->consent_email,
        'Telefon' => $member->consent_phone,
        'WhatsApp' => $member->consent_whatsapp,
        'Post' => $member->consent_post,
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-[2rem] bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8 sm:py-8">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                @if($member->photo)
                    <img src="{{ route('members.photo', $member) }}"
                         alt="Profilbild von {{ $member->full_name }}"
                         class="h-24 w-24 rounded-full border border-white/20 object-cover shadow-lg">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-white/10 text-3xl font-semibold text-white/80 ring-1 ring-white/10">
                        {{ \Illuminate\Support\Str::of($member->first_name)->substr(0, 1) }}{{ \Illuminate\Support\Str::of($member->last_name)->substr(0, 1) }}
                    </div>
                @endif

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-200">
                            Mitglied
                        </span>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusTone }}">
                            {{ ucfirst($member->status) }}
                        </span>
                    </div>

                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                        {{ $member->full_name ?: 'Unbenanntes Mitglied' }}
                    </h1>

                    <div class="mt-3 flex flex-col gap-2 text-sm text-slate-300 sm:flex-row sm:flex-wrap sm:items-center">
                        <span>{{ $member->email ?: 'Keine E-Mail hinterlegt' }}</span>
                        @if($member->mobile)
                            <span class="hidden sm:inline">·</span>
                            <span>{{ $member->mobile }}</span>
                        @endif
                        @if($member->membership?->name)
                            <span class="hidden sm:inline">·</span>
                            <span>{{ $member->membership->name }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                @if($canManageMembers)
                    <a href="{{ route('members.edit', $member) }}"
                       class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                        Bearbeiten
                    </a>
                    <a href="{{ route('members.datenauskunft', $member) }}"
                       class="inline-flex items-center justify-center rounded-full border border-white/20 bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15">
                        Datenauskunft
                    </a>
                @endif
                <a href="{{ route('members.index') }}"
                   class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    Zur Übersicht
                </a>
            </div>
        </div>
    </section>

    @if($member->is_archived)
        <section class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="font-semibold text-amber-950">Dieses Mitglied ist archiviert.</div>
                    <div class="mt-1 text-sm text-amber-800">
                        Archiviert am {{ optional($member->archived_at)->format('d.m.Y H:i') }}
                        @if($member->deletion_requested_at)
                            · Löschvormerkung seit {{ optional($member->deletion_requested_at)->format('d.m.Y') }}
                        @endif
                    </div>
                </div>
                @if($canManageMembers)
                    <form action="{{ route('members.restore', $member) }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                            Mitglied reaktivieren
                        </button>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <nav class="rounded-2xl border border-slate-200 bg-white px-5 py-4">
        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Orientierung</div>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="#stammdaten" class="rounded-full bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white">Stammdaten</a>
            <a href="#mitgliedschaft" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Mitgliedschaft</a>
            <a href="#pflichtstunden" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Pflichtstunden</a>
            <a href="#datenschutz" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Datenschutz</a>
            <a href="#finanzen" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Finanzen</a>
            <a href="#aktivitaet" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Aktivität</a>
        </div>
    </nav>

    <section id="stammdaten" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
        <div class="grid gap-6 xl:grid-cols-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Stammdaten</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Person und Kontakt</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Alles, was du brauchst, um das Mitglied eindeutig zu erkennen und zu erreichen.</p>

                @if($member->tags->isNotEmpty())
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach($member->tags as $tag)
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold text-slate-950" style="background-color: {{ $tag->color ?? '#E5E7EB' }}">
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="grid gap-8 lg:grid-cols-3 xl:col-span-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Profil</h3>
                    <dl class="mt-4 divide-y divide-slate-100 text-sm">
                        @foreach($profileDetails as $detail)
                            <div class="py-3 first:pt-0">
                                <dt class="text-slate-500">{{ $detail['label'] }}</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ $detail['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Kontakt</h3>
                    <dl class="mt-4 divide-y divide-slate-100 text-sm">
                        @foreach($contactDetails as $detail)
                            <div class="py-3 first:pt-0">
                                <dt class="text-slate-500">{{ $detail['label'] }}</dt>
                                <dd class="mt-1 break-words font-medium text-slate-900">
                                    @if(!empty($detail['link']) && !empty($detail['value']))
                                        <a href="{{ $detail['link'] }}" class="text-indigo-700 hover:text-indigo-900 hover:underline">{{ $detail['value'] }}</a>
                                    @else
                                        {{ $detail['value'] }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Adresse</h3>
                    <div class="mt-4 border-t border-slate-100 pt-3 text-sm leading-7 text-slate-900">
                        @if($addressLines !== [])
                            {{ implode("\n", $addressLines) }}
                        @else
                            Keine Adresse hinterlegt.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="mitgliedschaft" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
        <div class="grid gap-6 xl:grid-cols-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Mitgliedschaft</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Status, Termine und Zahlung</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Hier liegt alles, was zur laufenden Mitgliedschaft und Beitragszahlung gehört.</p>
            </div>

            <div class="grid gap-8 lg:grid-cols-2 xl:col-span-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Mitgliedschaftsdaten</h3>
                    <dl class="mt-4 divide-y divide-slate-100 text-sm">
                        @foreach($quickFacts as $fact)
                            <div class="py-3 first:pt-0">
                                <dt class="text-slate-500">{{ $fact['label'] }}</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ $fact['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Zahlung</h3>
                    @if($canManageFinance)
                        <dl class="mt-4 divide-y divide-slate-100 text-sm">
                            @foreach($paymentDetails as $detail)
                                <div class="py-3 first:pt-0">
                                    <dt class="text-slate-500">{{ $detail['label'] }}</dt>
                                    <dd class="mt-1 break-words font-medium text-slate-900">{{ $detail['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600">Zahlungs- und SEPA-Daten sind nur für Admins sichtbar.</p>
                    @endif

                    @php
                        $accountHolderAddress = collect([
                            $member->sepa_account_holder_street,
                            trim(($member->sepa_account_holder_zip ? $member->sepa_account_holder_zip . ' ' : '') . ($member->sepa_account_holder_city ?: '')),
                            $member->sepa_account_holder_country ? (config('countries.list')[$member->sepa_account_holder_country] ?? $member->sepa_account_holder_country) : null,
                        ])->filter()->implode(', ');
                    @endphp

                    @if($accountHolderAddress && $canManageFinance)
                        <div class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-700">
                            <div class="font-semibold text-slate-900">Kontoinhaberadresse</div>
                            <div class="mt-1">{{ $accountHolderAddress }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section id="pflichtstunden" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
        <div class="grid gap-6 xl:grid-cols-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Pflichtstunden</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Soll, geleistet, offen</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Gezählt werden nur Anwesenheiten, die bei einer Veranstaltung ausdrücklich als Pflichtstunden markiert wurden.</p>
            </div>

            <div class="space-y-5 xl:col-span-3">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 px-5 py-4">
                        <div class="text-sm font-medium text-slate-500">Soll</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($serviceHoursStats['required'], 2, ',', '.') }} h</div>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 px-5 py-4">
                        <div class="text-sm font-medium text-emerald-700">Geleistet</div>
                        <div class="mt-2 text-2xl font-semibold text-emerald-950">{{ number_format($serviceHoursStats['completed'], 2, ',', '.') }} h</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-5 py-4">
                        <div class="text-sm font-medium text-amber-700">Offen</div>
                        <div class="mt-2 text-2xl font-semibold text-amber-950">{{ number_format($serviceHoursStats['remaining'], 2, ',', '.') }} h</div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200">
                    <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-950">Gezählte Anwesenheiten</div>
                    <div class="divide-y divide-slate-100">
                        @forelse($serviceAttendances as $attendance)
                            <a href="{{ $attendance->event ? route('events.show', $attendance->event) : '#' }}" class="grid gap-2 px-4 py-3 text-sm hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr),120px] sm:items-center">
                                <div class="min-w-0">
                                    <div class="font-semibold text-slate-900">{{ $attendance->event?->title ?? 'Gelöschte Veranstaltung' }}</div>
                                    <div class="mt-1 text-slate-500">{{ optional($attendance->event?->start)->format('d.m.Y H:i') ?: 'ohne Datum' }}</div>
                                </div>
                                <div class="font-semibold text-slate-950 sm:text-right">{{ number_format((float) $attendance->hours, 2, ',', '.') }} h</div>
                            </a>
                        @empty
                            <div class="px-4 py-8 text-center text-sm text-slate-500">Noch keine gezählten Pflichtstunden vorhanden.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if($customFields->count())
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="grid gap-6 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Weitere Angaben</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Vereinsinterne Felder</h2>
                </div>

                <div class="grid gap-x-8 md:grid-cols-2 xl:col-span-3">
                    @foreach($customFields as $field)
                        @php
                            $value = optional($member->customValues->firstWhere('custom_member_field_id', $field->id))->value ?? 'Nicht hinterlegt';
                            if ($field->type === 'date' && $value !== 'Nicht hinterlegt') {
                                $value = \Carbon\Carbon::parse($value)->format('d.m.Y');
                            }
                            if ($field->type === 'select' && $value !== 'Nicht hinterlegt' && $field->options) {
                                $options = explode('|', $field->options);
                                $value = in_array($value, $options) ? $value : 'Nicht hinterlegt';
                            }
                        @endphp
                        <div class="border-t border-slate-100 py-3">
                            <div class="text-sm text-slate-500">{{ $field->label }}</div>
                            <div class="mt-1 text-sm font-medium text-slate-900">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="datenschutz" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
        <div class="grid gap-6 xl:grid-cols-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Datenschutz</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Freigaben und Aufbewahrung</h2>
                @if($canManageMembers)
                    <a href="{{ route('members.datenauskunft', $member) }}" class="mt-4 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-900 hover:underline">
                        Datenauskunft öffnen
                    </a>
                @endif
            </div>

            <div class="grid gap-8 lg:grid-cols-2 xl:col-span-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Kontaktfreigaben</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($contactPermissions as $label => $granted)
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $granted ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $granted ? '✓' : '–' }} {{ $label }}
                            </span>
                        @endforeach
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-4 text-sm">
                        <div class="text-slate-500">Einwilligung dokumentiert</div>
                        <div class="mt-1 font-medium text-slate-900">{{ $member->consent_given_at ? $member->consent_given_at->format('d.m.Y H:i') : 'Noch nicht hinterlegt' }}</div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Datenschutzstatus</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($privacyBadges as $label => $granted)
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $granted ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $granted ? '✓' : '–' }} {{ $label }}
                            </span>
                        @endforeach
                    </div>
                    <dl class="mt-5 divide-y divide-slate-100 text-sm">
                        <div class="py-3 first:pt-0">
                            <dt class="text-slate-500">Löschvormerkung</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $member->deletion_requested_at ? optional($member->deletion_requested_at)->format('d.m.Y H:i') : 'Keine vorgemerkt' }}</dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-slate-500">Archivstatus</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $member->is_archived ? 'Archiviert seit ' . optional($member->archived_at)->format('d.m.Y H:i') : 'Aktiv in der Mitgliederverwaltung' }}</dd>
                        </div>
                        @if($member->deletion_note)
                            <div class="py-3 last:pb-0">
                                <dt class="text-slate-500">Datenschutznotiz</dt>
                                <dd class="mt-1 whitespace-pre-line font-medium text-slate-900">{{ $member->deletion_note }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </section>

    @if($canManageMembers)
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="grid gap-6 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Kommunikation</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Kontakt protokollieren</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ $memberStats['communication_logs'] }} Einträge vorhanden.</p>
                </div>

                <form action="{{ route('members.communication-log.store', $member) }}" method="POST" class="space-y-4 xl:col-span-3">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="channel" class="mb-1 block text-sm font-medium text-slate-700">Kanal</label>
                            <select id="channel" name="channel" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]">
                                @foreach([
                                    'email' => 'E-Mail',
                                    'phone' => 'Telefon',
                                    'whatsapp' => 'WhatsApp',
                                    'post' => 'Post',
                                    'personal' => 'Persönlich',
                                    'system' => 'System',
                                ] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('channel', $member->preferred_contact_channel) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="direction" class="mb-1 block text-sm font-medium text-slate-700">Richtung</label>
                            <select id="direction" name="direction" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]">
                                <option value="outgoing" @selected(old('direction', 'outgoing') === 'outgoing')>Ausgehend</option>
                                <option value="incoming" @selected(old('direction') === 'incoming')>Eingehend</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="recipient" class="mb-1 block text-sm font-medium text-slate-700">Empfänger / Kontaktpunkt</label>
                            <input id="recipient" name="recipient" type="text" value="{{ old('recipient', $member->email ?: ($member->whatsapp_phone ?: $member->mobile)) }}" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]">
                        </div>
                        <div>
                            <label for="sent_at" class="mb-1 block text-sm font-medium text-slate-700">Zeitpunkt</label>
                            <input id="sent_at" name="sent_at" type="datetime-local" value="{{ old('sent_at', now()->format('Y-m-d\TH:i')) }}" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]">
                        </div>
                    </div>

                    <div>
                        <label for="subject" class="mb-1 block text-sm font-medium text-slate-700">Betreff</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]" placeholder="Kurz sagen, worum es ging">
                    </div>

                    <div>
                        <label for="message" class="mb-1 block text-sm font-medium text-slate-700">Notiz</label>
                        <textarea id="message" name="message" rows="4" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]" placeholder="Festhalten, was gesagt, gefragt oder vereinbart wurde.">{{ old('message') }}</textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-full bg-[#2954A3] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1E3F7F]">
                            Kommunikation speichern
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <section id="finanzen" class="grid gap-5 xl:grid-cols-2 scroll-mt-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Finanzen</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Rechnungen</h2>
                    @if($canManageFinance)
                        <div class="mt-2 text-sm text-slate-500">
                            Verfuegbares Guthaben:
                            <span class="font-semibold text-slate-900">{{ number_format((float) $memberStats['credit_balance'], 2, ',', '.') }} €</span>
                        </div>
                    @endif
                </div>
                @if($canManageFinance && $member->membership && ($member->membership_amount || $member->membership_interval) && !$member->is_archived)
                    <form action="{{ route('members.membership-invoice.store', $member) }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Beitragsrechnung anstoßen
                        </button>
                    </form>
                @endif
            </div>

            @if(!$canManageFinance)
                <p class="text-sm text-slate-500">Rechnungen und Zahlungsdaten sind nur für Admins sichtbar.</p>
            @else
                <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="max-w-xl">
                            <div class="text-sm font-semibold text-slate-900">Guthaben für Auslagen</div>
                            <p class="mt-1 text-sm text-slate-500">
                                Wenn ein Mitglied etwas für den Verein ausgelegt hat, kann der Betrag hier als Guthaben hinterlegt und bei der nächsten Beitragsrechnung automatisch verrechnet werden.
                            </p>
                        </div>

                        <form action="{{ route('members.credits.store', $member) }}" method="POST" class="grid gap-3 sm:grid-cols-2 lg:min-w-[420px]">
                            @csrf
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Wofür ist das Guthaben?</label>
                                <input name="description" type="text" value="{{ old('description') }}" placeholder="z. B. Material Renovierung Clubraum" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Betrag</label>
                                <input name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" placeholder="0,00" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Datum</label>
                                <input name="credited_at" type="date" value="{{ old('credited_at', now()->toDateString()) }}" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Notiz <span class="text-slate-400">(optional)</span></label>
                                <textarea name="notes" rows="2" class="w-full rounded-2xl border-slate-300 focus:border-[#2954A3] focus:ring-[#2954A3]" placeholder="z. B. Beleg liegt vor">{{ old('notes') }}</textarea>
                            </div>
                            <div class="sm:col-span-2 flex justify-end">
                                <button type="submit" class="rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                    Guthaben speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mb-5">
                    <div class="mb-3 text-sm font-semibold text-slate-900">Guthabenverlauf</div>
                    @if($credits->isEmpty())
                        <p class="text-sm text-slate-500">Noch kein Guthaben hinterlegt.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($credits as $credit)
                                <div class="border-t border-slate-100 py-4 first:border-t-0 first:pt-0 last:pb-0">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $credit->description }}</div>
                                            <div class="mt-1 text-sm text-slate-500">
                                                {{ optional($credit->credited_at)->format('d.m.Y') ?: 'ohne Datum' }}
                                                @if($credit->creator)
                                                    · erfasst von {{ $credit->creator->name }}
                                                @endif
                                            </div>
                                            @if($credit->notes)
                                                <div class="mt-2 text-sm text-slate-600">{{ $credit->notes }}</div>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <div class="font-semibold text-slate-900">{{ number_format((float) $credit->amount, 2, ',', '.') }} €</div>
                                            <div class="mt-1 text-xs {{ $credit->remaining_amount > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                                                Noch offen: {{ number_format((float) $credit->remaining_amount, 2, ',', '.') }} €
                                            </div>
                                        </div>
                                    </div>
                                    @if($credit->applications->isNotEmpty())
                                        <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                                            @foreach($credit->applications as $application)
                                                <div>
                                                    Verrechnet mit
                                                    @if($application->invoice)
                                                        <a href="{{ route('invoices.show', $application->invoice) }}" class="font-medium text-slate-700 hover:text-slate-900">
                                                            {{ $application->invoice->invoice_number }}
                                                        </a>
                                                    @else
                                                        einer Rechnung
                                                    @endif
                                                    · {{ number_format((float) $application->amount, 2, ',', '.') }} €
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            @if($invoices->isEmpty())
                <p class="text-sm text-slate-500">Bisher keine Rechnungen vorhanden.</p>
            @else
                <div class="space-y-3">
                    @foreach($invoices as $invoice)
                        <a href="{{ route('invoices.show', $invoice) }}" class="block border-t border-slate-100 py-4 transition first:border-t-0 first:pt-0 last:pb-0 hover:bg-slate-50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="font-medium text-slate-900">{{ $invoice->invoice_number }}</div>
                                    <div class="mt-1 text-sm text-slate-500">
                                        {{ optional($invoice->invoice_date)->format('d.m.Y') ?: 'ohne Datum' }}
                                        @if($invoice->due_date)
                                            · fällig {{ optional($invoice->due_date)->format('d.m.Y') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-slate-900">{{ number_format($invoice->getTotal(), 2, ',', '.') }} €</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ ucfirst($invoice->status ?? 'entwurf') }}</div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Teilnahme</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Events und Kurse</h2>
                </div>
                <span class="text-sm text-slate-500">{{ $memberStats['event_registrations'] }} gefunden</span>
            </div>

            @if($eventRegistrations->isEmpty())
                <p class="text-sm text-slate-500">Noch keine Eventanmeldungen gefunden.</p>
            @else
                <div class="space-y-3">
                    @foreach($eventRegistrations as $registration)
                        <a href="{{ $registration->event ? route('events.show', $registration->event) : '#' }}"
                           class="block border-t border-slate-100 py-4 transition first:border-t-0 first:pt-0 last:pb-0 hover:bg-slate-50">
                            <div class="font-medium text-slate-900">{{ $registration->event?->title ?? 'Event-Anmeldung' }}</div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ optional($registration->event?->start)->format('d.m.Y H:i') ?: 'ohne Termin' }}
                                @if($registration->event?->location)
                                    · {{ $registration->event->location }}
                                @endif
                            </div>
                            <div class="mt-2 text-xs text-slate-500">
                                Anmeldung am {{ $registration->created_at->format('d.m.Y H:i') }}
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="aktivitaet" class="rounded-2xl border border-slate-200 bg-white p-5 scroll-mt-6">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Verlauf</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Kommunikationshistorie</h2>
            </div>
            <span class="text-sm text-slate-500">{{ $memberStats['communication_logs'] }} Einträge</span>
        </div>

        @if($communicationLogs->isEmpty())
            <p class="text-sm text-slate-500">Bisher wurde noch keine Kommunikation protokolliert.</p>
        @else
            <div class="space-y-3">
                @foreach($communicationLogs as $log)
                    <div class="border-t border-slate-100 py-4 first:border-t-0 first:pt-0 last:pb-0">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="font-medium text-slate-900">{{ $log->subject ?: 'Ohne Betreff' }}</div>
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ strtoupper($log->channel) }} · {{ $log->direction === 'incoming' ? 'Eingehend' : 'Ausgehend' }}
                                    @if($log->recipient)
                                        · {{ $log->recipient }}
                                    @endif
                                    @if($log->creator)
                                        · von {{ $log->creator->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-sm text-slate-500">
                                {{ optional($log->sent_at ?? $log->created_at)->format('d.m.Y H:i') }}
                            </div>
                        </div>

                        @if($log->message)
                            <div class="mt-3 border-l-2 border-slate-200 pl-4 text-sm whitespace-pre-line text-slate-700">
                                {{ $log->message }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Timeline</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Letzte Aktivitäten</h2>
            </div>
            <span class="text-sm text-slate-500">{{ $activity->count() }} Einträge</span>
        </div>

        @if($activity->isEmpty())
            <p class="text-sm text-slate-500">Bisher keine Aktivitäten sichtbar.</p>
        @else
            <div class="space-y-3">
                @foreach($activity as $entry)
                    <a @if($entry['route']) href="{{ $entry['route'] }}" @endif class="flex flex-col gap-2 border-t border-slate-100 py-4 transition first:border-t-0 first:pt-0 last:pb-0 hover:bg-slate-50 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900">{{ $entry['title'] }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $entry['subtitle'] }}</div>
                        </div>
                        <div class="text-sm text-slate-500 sm:text-right">
                            <div>{{ $entry['meta'] }}</div>
                            @if($entry['date'])
                                <div class="mt-1">{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('d.m.Y H:i') }}</div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
