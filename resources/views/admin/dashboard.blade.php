@extends('layouts.app')

@section('title', 'Clubano Cockpit')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-600">Clubano Betrieb</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-normal text-slate-950">Admin-Cockpit</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                Eine 360-Grad-Sicht auf alle Vereine: Wachstum, Aktivität, Lizenzen, Nutzungstiefe und die Vereine, die deine Aufmerksamkeit brauchen.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.account') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                Betreiberkonto
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @foreach([
            ['label' => 'Vereine', 'value' => $platformStats['tenants'], 'hint' => $lifecycleStats['new_30_days'].' neu in 30 Tagen'],
            ['label' => 'Aktiv', 'value' => $lifecycleStats['active_30_days'], 'hint' => 'Aktivität in 30 Tagen'],
            ['label' => 'Prüfung offen', 'value' => $platformStats['verification_pending'], 'hint' => 'neue Vereine prüfen'],
            ['label' => 'Risiko', 'value' => $platformStats['verification_risk'], 'hint' => 'markiert oder abgelehnt'],
            ['label' => 'Mit Ort', 'value' => $lifecycleStats['with_location'], 'hint' => 'Vereinsprofil gepflegt'],
            ['label' => 'Importe', 'value' => $lifecycleStats['with_imports'], 'hint' => 'Umstieg begonnen'],
        ] as $stat)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ number_format($stat['value'], 0, ',', '.') }}</div>
                <div class="mt-2 text-xs font-medium text-slate-400">{{ $stat['hint'] }}</div>
            </div>
        @endforeach
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Plattform-Radar</h2>
                    <p class="mt-1 text-sm text-slate-500">Der Zustand der Plattform in sechs klaren Signalen.</p>
                </div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">360 Grad</span>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                @foreach([
                    ['label' => 'Wachstum', 'value' => $lifecycleStats['new_7_days'], 'hint' => 'neue Vereine in 7 Tagen'],
                    ['label' => 'Onboarding', 'value' => $lifecycleStats['with_members'], 'hint' => 'Vereine mit Mitgliedern'],
                    ['label' => 'Kalendernutzung', 'value' => $lifecycleStats['with_events'], 'hint' => 'Vereine mit Terminen'],
                    ['label' => 'Lizenzen', 'value' => $platformStats['licensed'], 'hint' => 'Pilot- und Freilizenzen'],
                    ['label' => 'Trials prüfen', 'value' => $platformStats['expired_trials'], 'hint' => 'abgelaufene Tests'],
                    ['label' => 'Benutzer', 'value' => $platformStats['users'], 'hint' => 'alle Betreiber und Vereine'],
                ] as $signal)
                    <div class="rounded-xl bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $signal['label'] }}</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($signal['value'], 0, ',', '.') }}</div>
                        <div class="mt-1 text-sm text-slate-500">{{ $signal['hint'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Handlungsbedarf</h2>
                    <p class="mt-1 text-sm text-slate-500">Was du als Betreiber zuerst ansehen solltest.</p>
                </div>
                <span class="text-2xl font-semibold text-slate-950">{{ $attentionTenants->count() }}</span>
            </div>

            <div class="mt-5 space-y-4">
                @forelse($attentionTenants as $tenant)
                    @php
                        $health = $tenant->admin_health;
                    @endphp
                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="block rounded-xl border border-slate-200 px-4 py-3 transition hover:border-blue-200 hover:bg-blue-50/40">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-slate-950">{{ $tenant->name ?: 'Unbenannter Verein' }}</div>
                                <div class="mt-0.5 text-xs font-semibold text-slate-400">{{ $tenant->admin_profile['location'] }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $health['reason'] }}</div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $health['level'] === 'risk' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ $health['label'] }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                        Kein akuter Handlungsbedarf sichtbar.
                    </div>
                @endforelse
            </div>
        </aside>
    </section>

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Alle Vereine</h2>
                <p class="mt-1 text-sm text-slate-500">Vollständiger Bestand mit Status, Nutzung und schnellem Zugriff.</p>
            </div>
            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $allTenants->count() }} Vereine</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Verein</th>
                        <th class="px-5 py-3">Ort & Kontakt</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Nutzung</th>
                        <th class="px-5 py-3">Letzte Aktivität</th>
                        <th class="px-5 py-3">Lizenz</th>
                        <th class="px-5 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($allTenants as $tenant)
                        @php
                            $metrics = $tenant->admin_metrics ?? [];
                            $health = $tenant->admin_health;
                            $profile = $tenant->admin_profile;
                            $features = $tenant->admin_feature_state;
                            $review = $tenant->admin_registration_review;
                        @endphp
                        <tr class="align-top">
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.tenants.show', $tenant) }}" class="font-semibold text-slate-950 hover:text-blue-700">{{ $tenant->name ?: 'Unbenannter Verein' }}</a>
                                <div class="mt-1 text-sm text-slate-500">{{ $profile['age'] }} registriert</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $tenant->registration_contact_name ?: 'Ansprechpartner fehlt' }}</div>
                                <div class="mt-1 text-xs text-slate-400">seit {{ optional($tenant->created_at)->format('d.m.Y') }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="min-w-[220px]">
                                    <div class="font-semibold {{ $profile['location'] === 'Ort fehlt' ? 'text-amber-700' : 'text-slate-950' }}">{{ $profile['location'] }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ $profile['address'] }}</div>
                                    <div class="mt-1 break-all text-xs text-slate-400">{{ $profile['contact'] }}</div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $health['level'] === 'ok' ? 'bg-emerald-50 text-emerald-700' : ($health['level'] === 'risk' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                    {{ $health['label'] }}
                                </span>
                                <span class="ml-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $review['level'] === 'ok' ? 'bg-emerald-50 text-emerald-700' : ($review['level'] === 'risk' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                    {{ $tenant->verification_status_label }}
                                </span>
                                <div class="mt-2 max-w-xs text-xs leading-5 text-slate-500">{{ $health['reason'] }}</div>
                                @if(!empty($review['reasons']))
                                    <div class="mt-1 max-w-xs text-xs leading-5 text-slate-500">{{ $review['reasons'][0] }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="grid min-w-[300px] grid-cols-3 gap-2">
                                    @foreach([
                                        'Aktiv' => $metrics['active_members'] ?? 0,
                                        'Benutzer' => $metrics['users'] ?? 0,
                                        'Termine' => $metrics['events'] ?? 0,
                                        'Dokumente' => $metrics['documents'] ?? 0,
                                        'Protokolle' => $metrics['protocols'] ?? 0,
                                        'Importe' => $metrics['imports'] ?? 0,
                                    ] as $label => $value)
                                        <div>
                                            <div class="font-semibold text-slate-950">{{ number_format($value, 0, ',', '.') }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $label }}</div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-3 flex min-w-[300px] flex-wrap gap-1.5">
                                    <span class="rounded-md px-2 py-1 text-[11px] font-semibold {{ $tenant->verification_status_tone === 'ok' ? 'bg-emerald-50 text-emerald-700' : ($tenant->verification_status_tone === 'risk' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                        {{ $tenant->verification_status_label }}
                                    </span>
                                    @foreach($features as $feature)
                                        <span class="rounded-md px-2 py-1 text-[11px] font-semibold {{ $feature['state'] === 'ok' ? 'bg-emerald-50 text-emerald-700' : ($feature['state'] === 'watch' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-500') }}">
                                            {{ $feature['label'] }} {{ $feature['value'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ $tenant->admin_last_activity_at ? $tenant->admin_last_activity_at->diffForHumans() : 'keine Aktivität' }}
                            </td>
                            <td class="px-5 py-4">
                                <form method="POST" action="{{ route('admin.tenants.license', $tenant) }}" class="flex min-w-[260px] flex-col gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="license_mode" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="standard" @selected(($tenant->license_mode ?? 'standard') === 'standard')>Standard</option>
                                        <option value="beta" @selected(($tenant->license_mode ?? 'standard') === 'beta')>Pilotlizenz</option>
                                        <option value="gifted" @selected(($tenant->license_mode ?? 'standard') === 'gifted')>Freilizenz</option>
                                    </select>
                                    <div class="flex gap-2">
                                        <input type="date" name="license_expires_at" value="{{ optional($tenant->license_expires_at)->format('Y-m-d') }}" class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <button type="submit" class="rounded-xl bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">OK</button>
                                    </div>
                                </form>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Details</a>
                                    @if($tenant->email)
                                        <a href="mailto:{{ $tenant->email }}?subject=Willkommen bei Clubano" class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">Mail</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">Noch keine Vereine vorhanden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Neueste Registrierungen</h2>
            <div class="mt-5 space-y-4">
                @forelse($latestTenants as $tenant)
                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3 transition hover:bg-blue-50">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-slate-950">{{ $tenant->name ?: 'Unbenannter Verein' }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $tenant->admin_profile['location'] }} · {{ optional($tenant->created_at)->format('d.m.Y H:i') }}</div>
                            <div class="mt-1 text-xs text-slate-400">{{ $tenant->verification_status_label }} · {{ $tenant->registration_contact_name ?: 'Ansprechpartner fehlt' }}</div>
                        </div>
                        <span class="text-sm font-semibold text-slate-500">{{ ($tenant->admin_metrics['members'] ?? 0) }} Mitglieder</span>
                    </a>
                @empty
                    <div class="text-sm text-slate-500">Noch keine Registrierungen vorhanden.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Neueste Benutzer</h2>
            <div class="mt-5 space-y-4">
                @forelse($latestUsers as $user)
                    <div>
                        <div class="font-semibold text-slate-900">{{ $user->name ?: 'Ohne Namen' }}</div>
                        <div class="mt-0.5 break-all text-sm text-slate-500">{{ $user->email }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ $user->tenant?->name ?: 'Betreiberkonto' }} · {{ optional($user->created_at)->format('d.m.Y H:i') }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Noch keine Benutzer vorhanden.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
