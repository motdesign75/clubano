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
            ['label' => 'Ohne Mitglieder', 'value' => $lifecycleStats['without_members'], 'hint' => 'Onboarding prüfen'],
            ['label' => 'Supportbereit', 'value' => $lifecycleStats['support_ready'], 'hint' => 'geprüft und aktiv'],
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
                    ['label' => 'Mitgliederstart', 'value' => $lifecycleStats['member_onboarding_risk'], 'hint' => 'brauchen Unterstützung'],
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
                <p class="mt-1 text-sm text-slate-500">Suchen, filtern und gezielt in den nächsten Supportfall springen.</p>
            </div>
            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                {{ $filteredTenants->count() }} von {{ $allTenants->count() }} Vereinen
            </span>
        </div>

        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                <div>
                    <label for="tenant-search" class="sr-only">Verein suchen</label>
                    <input
                        id="tenant-search"
                        type="search"
                        name="q"
                        value="{{ $tenantSearch }}"
                        placeholder="Verein, Ort, E-Mail oder Ansprechpartner suchen"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label for="tenant-status" class="sr-only">Status filtern</label>
                    <select id="tenant-status" name="status" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="all" @selected($tenantStatus === 'all')>Alle Vereine</option>
                        <option value="attention" @selected($tenantStatus === 'attention')>Handlungsbedarf</option>
                        <option value="pending" @selected($tenantStatus === 'pending')>Prüfung offen</option>
                        <option value="verified" @selected($tenantStatus === 'verified')>Geprüft</option>
                        <option value="active" @selected($tenantStatus === 'active')>Aktiv in 30 Tagen</option>
                        <option value="without_admin" @selected($tenantStatus === 'without_admin')>Ohne Admin</option>
                        <option value="without_members" @selected($tenantStatus === 'without_members')>Ohne Mitglieder</option>
                    </select>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[auto_auto]">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Anwenden
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Zurücksetzen
                    </a>
                </div>
            </form>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach([
                    'all' => 'Alle',
                    'attention' => 'Handlungsbedarf',
                    'pending' => 'Prüfung offen',
                    'active' => 'Aktiv',
                    'without_admin' => 'Ohne Admin',
                    'without_members' => 'Ohne Mitglieder',
                ] as $statusKey => $statusLabel)
                    <a
                        href="{{ route('admin.dashboard', array_filter(['q' => $tenantSearch ?: null, 'status' => $statusKey === 'all' ? null : $statusKey])) }}"
                        class="rounded-full px-3 py-1 text-xs font-semibold transition {{ $tenantStatus === $statusKey ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-blue-50 hover:text-blue-700' }}"
                    >
                        {{ $statusLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($paginatedTenants as $tenant)
                @php
                    $metrics = $tenant->admin_metrics ?? [];
                    $health = $tenant->admin_health;
                    $profile = $tenant->admin_profile;
                    $review = $tenant->admin_registration_review;
                    $memberOnboarding = $tenant->admin_member_onboarding;
                    $usageStats = [
                        ['label' => 'Mitglieder', 'value' => $metrics['active_members'] ?? 0],
                        ['label' => 'Benutzer', 'value' => $metrics['users'] ?? 0],
                        ['label' => 'Termine', 'value' => $metrics['events'] ?? 0],
                        ['label' => 'Importe', 'value' => $metrics['imports'] ?? 0],
                    ];
                    $primaryIssue = $health['reason'];

                    if (($metrics['admin_users'] ?? 0) === 0) {
                        $primaryIssue = 'Kein Vereinsadmin mit Benutzerzugang verknüpft.';
                    } elseif (($metrics['members'] ?? 0) === 0) {
                        $primaryIssue = $memberOnboarding['reason'] ?? 'Noch keine Mitglieder angelegt.';
                    } elseif (($review['level'] ?? 'ok') !== 'ok' && ! empty($review['reasons'])) {
                        $primaryIssue = $review['reasons'][0];
                    }

                    $rowTone = $health['level'] === 'risk'
                        ? 'border-rose-200 bg-rose-50/30'
                        : (($review['level'] ?? 'ok') !== 'ok' ? 'border-amber-200 bg-amber-50/30' : 'border-transparent bg-white');
                @endphp

                <article class="px-5 py-5">
                    <div class="rounded-2xl border {{ $rowTone }} p-4">
                        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.95fr)_minmax(300px,0.9fr)] xl:items-start">
                            <div class="min-w-0">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.tenants.show', $tenant) }}" class="block truncate text-lg font-semibold text-slate-950 hover:text-blue-700">
                                            {{ $tenant->name ?: 'Unbenannter Verein' }}
                                        </a>
                                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-sm text-slate-500">
                                            <span>{{ $profile['location'] }}</span>
                                            <span class="text-slate-300">·</span>
                                            <span>{{ $profile['age'] }} registriert</span>
                                            <span class="text-slate-300">·</span>
                                            <span>{{ $tenant->admin_last_activity_at ? 'aktiv '.$tenant->admin_last_activity_at->diffForHumans() : 'noch keine Aktivität' }}</span>
                                        </div>
                                    </div>
                                    <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $health['level'] === 'ok' ? 'bg-emerald-50 text-emerald-700' : ($health['level'] === 'risk' ? 'bg-rose-100 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                        {{ $health['label'] }}
                                    </span>
                                </div>

                                <div class="mt-4 rounded-xl border border-slate-200 bg-white/80 px-4 py-3">
                                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Nächster Blick</div>
                                    <p class="mt-1 text-sm leading-6 text-slate-700">{{ $primaryIssue }}</p>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Ansprechpartner</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $tenant->registration_contact_name ?: 'fehlt' }}</div>
                                        <div class="mt-0.5 text-sm text-slate-500">{{ $tenant->registration_role ?: 'Funktion fehlt' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Kontakt</div>
                                        <div class="mt-1 break-all text-sm text-slate-600">{{ $profile['contact'] }}</div>
                                        <div class="mt-0.5 text-sm text-slate-500">{{ $profile['address'] }}</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($usageStats as $stat)
                                        <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-slate-200">
                                            <div class="text-2xl font-semibold text-slate-950">{{ number_format($stat['value'], 0, ',', '.') }}</div>
                                            <div class="mt-1 text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">{{ $stat['label'] }}</div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tenant->verification_status_tone === 'ok' ? 'bg-emerald-50 text-emerald-700' : ($tenant->verification_status_tone === 'risk' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                        {{ $tenant->verification_status_label }}
                                    </span>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ ($metrics['documents'] ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        Dokumente {{ $metrics['documents'] ?? 0 }}
                                    </span>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ ($metrics['accounts'] ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        Finanzen {{ $metrics['accounts'] ?? 0 }}
                                    </span>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ ($metrics['donations'] ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        Spenden {{ ($metrics['donations'] ?? 0) > 0 ? 'an' : 'aus' }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Lizenz</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-950">
                                            {{ match($tenant->license_mode ?? 'standard') {
                                                'beta' => 'Pilotlizenz',
                                                'gifted' => 'Freilizenz',
                                                default => 'Standard',
                                            } }}
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-slate-500">
                                        {{ optional($tenant->license_expires_at)->format('d.m.Y') ?: 'kein Ablaufdatum' }}
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('admin.tenants.license', $tenant) }}" class="mt-4 space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="license_mode" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="standard" @selected(($tenant->license_mode ?? 'standard') === 'standard')>Standard</option>
                                        <option value="beta" @selected(($tenant->license_mode ?? 'standard') === 'beta')>Pilotlizenz</option>
                                        <option value="gifted" @selected(($tenant->license_mode ?? 'standard') === 'gifted')>Freilizenz</option>
                                    </select>
                                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                        <input type="date" name="license_expires_at" value="{{ optional($tenant->license_expires_at)->format('Y-m-d') }}" class="min-w-0 rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-semibold text-white transition hover:bg-slate-800">
                                            Speichern
                                        </button>
                                    </div>
                                </form>

                                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Details öffnen
                                    </a>
                                    @if($tenant->email)
                                        <a href="mailto:{{ $tenant->email }}?subject=Willkommen bei Clubano" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                            Mail schreiben
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="px-5 py-12 text-center text-sm text-slate-500">
                    Keine Vereine für diese Suche gefunden.
                </div>
            @endforelse
        </div>

        @if($paginatedTenants->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $paginatedTenants->links() }}
            </div>
        @endif
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-3">
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

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Betreiberprotokoll</h2>
            <p class="mt-1 text-sm text-slate-500">Letzte Änderungen im Admin-Cockpit.</p>
            <div class="mt-5 space-y-4">
                @forelse($latestAuditLogs as $log)
                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                        <div class="font-semibold text-slate-900">{{ $log->label ?: $log->action }}</div>
                        <div class="mt-1 text-sm text-slate-500">
                            {{ $log->target_tenant_name ?: 'Betreiber' }} · {{ $log->actor_name ?: $log->actor_email ?: 'System' }}
                        </div>
                        <div class="mt-1 text-xs text-slate-400">{{ optional($log->created_at)->format('d.m.Y H:i') }}</div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Noch keine Betreiberaktionen protokolliert.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
