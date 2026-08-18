@extends('layouts.app')

@section('title', 'Verein')

@section('content')
@php
    $user = auth()->user();
    $canEditTenant = $user?->canManageTenantSettings() ?? false;
    $addressLine = collect([$tenant->address, trim(($tenant->zip ?? '') . ' ' . ($tenant->city ?? ''))])
        ->filter(fn ($value) => filled($value))
        ->implode(', ');
    $hasAddress = filled($addressLine);
    $hasBankDetails = filled($tenant->iban) || filled($tenant->bic) || filled($tenant->bank_name) || filled($tenant->creditor_identifier);
    $hasLetterhead = filled($tenant->pdf_template);
    $letterheadExtension = $hasLetterhead ? strtolower(pathinfo($tenant->pdf_template, PATHINFO_EXTENSION)) : null;
    $licenseLabel = match (true) {
        $tenant->hasComplimentaryAccess() => $tenant->license_mode_label,
        $tenant->onTrial() && $tenant->trial_ends_at => 'Testphase bis ' . $tenant->trial_ends_at->format('d.m.Y'),
        $tenant->subscribed('default') => 'Lizenz aktiv',
        default => 'Lizenz prüfen',
    };

    $readiness = [
        [
            'label' => 'Stammdaten',
            'value' => filled($tenant->name) && filled($tenant->email) && $hasAddress ? 'vollständig' : 'prüfen',
            'active' => filled($tenant->name) && filled($tenant->email) && $hasAddress,
            'hint' => 'Name, E-Mail und Adresse',
            'icon' => 'building',
        ],
        [
            'label' => 'Bank & SEPA',
            'value' => $hasBankDetails ? 'hinterlegt' : 'fehlt',
            'active' => $hasBankDetails,
            'hint' => 'IBAN, BIC, Bank oder Gläubiger-ID',
            'icon' => 'bank',
        ],
        [
            'label' => 'Briefbogen',
            'value' => $hasLetterhead ? 'bereit' : 'fehlt',
            'active' => $hasLetterhead,
            'hint' => 'Vorlage für PDFs',
            'icon' => 'document',
        ],
        [
            'label' => 'Austrittsmail',
            'value' => $tenant->member_exit_mail_enabled ? 'aktiv' : 'aus',
            'active' => $tenant->member_exit_mail_enabled,
            'hint' => 'Automatische Bestätigung',
            'icon' => 'mail',
        ],
    ];

    $profileRows = [
        ['label' => 'Vereinsname', 'value' => $tenant->name],
        ['label' => 'E-Mail', 'value' => $tenant->email, 'type' => 'mail'],
        ['label' => 'Telefon', 'value' => $tenant->phone, 'type' => 'phone'],
        ['label' => 'Adresse', 'value' => $hasAddress ? $addressLine : null],
        ['label' => 'Vereinsregister', 'value' => $tenant->register_number],
        ['label' => 'Vorsitz', 'value' => $tenant->chairman_name],
    ];

    $bankRows = [
        ['label' => 'Bank', 'value' => $tenant->bank_name],
        ['label' => 'IBAN', 'value' => $tenant->iban],
        ['label' => 'BIC', 'value' => $tenant->bic],
        ['label' => 'Gläubiger-ID', 'value' => $tenant->creditor_identifier],
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_52%,#1f2937_100%)] p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr),360px] lg:p-10">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Verein verwalten</div>
                <div class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-center">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-white/15 bg-white/10">
                        @if($tenant->logo_url)
                            <img src="{{ route('tenant.logo') }}" alt="Logo {{ $tenant->name }}" class="h-full w-full object-contain p-2">
                        @else
                            <x-heroicon-o-building-office-2 class="h-9 w-9 text-white/75" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $tenant->name }}</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-white/68">
                            Hier liegen die offiziellen Angaben deines Vereins. Diese Daten tauchen an vielen Stellen wieder auf, zum Beispiel in PDFs, E-Mails, Rechnungen und Nachweisen.
                        </p>
                    </div>
                </div>

                <div class="mt-7 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">
                        {{ $licenseLabel }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">
                        {{ $tenant->verification_status_label }}
                    </span>
                    @if($tenant->city)
                        <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">
                            {{ $tenant->city }}
                        </span>
                    @endif
                </div>
            </div>

            <aside class="rounded-xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Schnell prüfen</div>
                <div class="mt-4 space-y-3">
                    @foreach($readiness as $item)
                        <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $item['active'] ? 'bg-white text-slate-950' : 'bg-white/10 text-white/70' }}">
                                @if($item['icon'] === 'bank')
                                    <x-heroicon-o-banknotes class="h-5 w-5" />
                                @elseif($item['icon'] === 'document')
                                    <x-heroicon-o-document-text class="h-5 w-5" />
                                @elseif($item['icon'] === 'mail')
                                    <x-heroicon-o-envelope class="h-5 w-5" />
                                @else
                                    <x-heroicon-o-building-office class="h-5 w-5" />
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-white">{{ $item['label'] }}</span>
                                <span class="block truncate text-xs text-white/55">{{ $item['hint'] }}</span>
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $item['active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $item['value'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </aside>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr),380px]">
        <section class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Vereinsprofil</div>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Offizielle Angaben</h2>
                    </div>
                    @if($canEditTenant)
                        <a href="{{ route('tenant.edit') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                            <x-heroicon-o-pencil-square class="h-5 w-5" />
                            Bearbeiten
                        </a>
                    @endif
                </div>

                <dl class="mt-6 divide-y divide-slate-100">
                    @foreach($profileRows as $row)
                        <div class="grid gap-2 py-4 first:pt-0 last:pb-0 sm:grid-cols-[180px,minmax(0,1fr)]">
                            <dt class="text-sm font-semibold text-slate-500">{{ $row['label'] }}</dt>
                            <dd class="min-w-0 text-sm leading-6 text-slate-950">
                                @if(filled($row['value']))
                                    @if(($row['type'] ?? null) === 'mail')
                                        <a href="mailto:{{ $row['value'] }}" class="font-medium text-blue-700 hover:text-blue-800">{{ $row['value'] }}</a>
                                    @elseif(($row['type'] ?? null) === 'phone')
                                        <a href="tel:{{ $row['value'] }}" class="font-medium text-blue-700 hover:text-blue-800">{{ $row['value'] }}</a>
                                    @else
                                        {{ $row['value'] }}
                                    @endif
                                @else
                                    <span class="text-amber-700">fehlt</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <x-heroicon-o-banknotes class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Bank & SEPA</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Wichtig für Beiträge, Rechnungen und Lastschriften.</p>
                        </div>
                    </div>

                    <dl class="mt-5 space-y-3">
                        @foreach($bankRows as $row)
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $row['label'] }}</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-950">{{ filled($row['value']) ? $row['value'] : 'fehlt' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <x-heroicon-o-document-text class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Briefbogen & PDFs</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">So erkennt man offizielle Dokumente deines Vereins.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        @if($hasLetterhead)
                            @if(in_array($letterheadExtension, ['jpg', 'jpeg', 'png'], true))
                                <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                    <img src="{{ route('tenant.letterhead') }}" alt="Briefbogen" class="max-h-52 w-full object-contain p-3">
                                </div>
                            @else
                                <a href="{{ route('tenant.letterhead') }}" target="_blank" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                                    Briefbogen öffnen
                                </a>
                            @endif
                        @else
                            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm leading-6 text-slate-500">
                                Es ist noch kein Briefbogen hinterlegt.
                            </div>
                        @endif

                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">In PDFs verwenden</div>
                            <div class="mt-1 text-sm font-semibold {{ $tenant->use_letterhead ? 'text-emerald-700' : 'text-slate-700' }}">
                                {{ $tenant->use_letterhead ? 'Ja, Briefbogen wird verwendet' : 'Nein, aktuell nicht aktiv' }}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Kommunikation</h2>
                <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Austrittsbestätigung</div>
                    <div class="mt-1 text-sm font-semibold {{ $tenant->member_exit_mail_enabled ? 'text-emerald-700' : 'text-slate-700' }}">
                        {{ $tenant->member_exit_mail_enabled ? 'Automatisch aktiv' : 'Nicht aktiviert' }}
                    </div>
                    @if($tenant->member_exit_mail_subject)
                        <div class="mt-2 text-sm leading-6 text-slate-500">{{ $tenant->member_exit_mail_subject }}</div>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Was als Nächstes sinnvoll ist</h2>
                <div class="mt-4 space-y-3">
                    @foreach($readiness as $item)
                        @unless($item['active'])
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <div class="text-sm font-semibold text-amber-950">{{ $item['label'] }} ergänzen</div>
                                <div class="mt-1 text-sm leading-5 text-amber-800">{{ $item['hint'] }}</div>
                            </div>
                        @endunless
                    @endforeach

                    @if(collect($readiness)->every(fn ($item) => $item['active']))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <div class="text-sm font-semibold text-emerald-950">Alles Wesentliche ist gepflegt</div>
                            <div class="mt-1 text-sm leading-5 text-emerald-800">Die Vereinsdaten wirken bereit für den laufenden Betrieb.</div>
                        </div>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
