@extends('layouts.app')

@section('title', 'Verein prüfen')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">
            Zurück zum Admin-Cockpit
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <main class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-600">Verein</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-normal text-slate-950">{{ $tenant->name ?: 'Unbenannter Verein' }}</h1>
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                            <span>{{ $tenant->email ?: 'keine E-Mail' }}</span>
                            <span>{{ $tenantProfile['location'] }}</span>
                            <span>registriert {{ optional($tenant->created_at)->format('d.m.Y H:i') }}</span>
                        </div>
                    </div>

                    <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $tenant->hasComplimentaryAccess() || $tenant->subscribed('default') || $tenant->trial_ends_at?->isFuture() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $tenant->license_mode_label }}
                    </span>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach([
                        'Mitglieder aktiv' => $stats['active_members'],
                        'Benutzer' => $stats['users'],
                        'Termine geplant' => $stats['upcoming_events'],
                        'Importe' => $stats['imports'],
                    ] as $label => $value)
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $label }}</dt>
                            <dd class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($value, 0, ',', '.') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Vereinsprofil</h2>
                    <div class="mt-5 divide-y divide-slate-100 text-sm">
                        @foreach([
                            'Ort' => $tenantProfile['location'],
                            'Adresse' => $tenantProfile['address'],
                            'E-Mail' => $tenantProfile['contact'],
                            'Telefon' => $tenantProfile['phone'],
                            'Vereinsregister' => $tenant->register_number ?: 'fehlt',
                        ] as $label => $value)
                            <div class="grid gap-1 py-3 sm:grid-cols-[9rem_1fr]">
                                <div class="font-semibold text-slate-900">{{ $label }}</div>
                                <div class="{{ str_contains($value, 'fehlt') ? 'text-amber-700' : 'text-slate-600' }}">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Clubano-Reifegrad</h2>
                    <p class="mt-1 text-sm text-slate-500">Welche Bereiche der Verein bereits nutzt.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach($featureState as $feature)
                            <div class="rounded-xl border {{ $feature['state'] === 'ok' ? 'border-emerald-200 bg-emerald-50' : ($feature['state'] === 'watch' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50') }} px-4 py-3">
                                <div class="text-sm font-semibold {{ $feature['state'] === 'ok' ? 'text-emerald-800' : ($feature['state'] === 'watch' ? 'text-amber-800' : 'text-slate-600') }}">{{ $feature['label'] }}</div>
                                <div class="mt-1 text-xl font-semibold text-slate-950">{{ $feature['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Nutzung</h2>
                        <p class="mt-1 text-sm text-slate-500">Was der Verein bereits in Clubano angelegt hat.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach([
                        'Mitglieder gesamt' => $stats['members'],
                        'Archivierte Mitglieder' => $stats['archived_members'],
                        'Termine gesamt' => $stats['events'],
                        'Formulare' => $stats['forms'],
                        'Protokolle' => $stats['protocols'],
                        'Aufgaben' => $stats['tasks'],
                        'Einladungen' => $stats['invitations'],
                        'Finanzkonten' => $stats['accounts'],
                        'Spenden' => $stats['donations'],
                    ] as $label => $value)
                        <div class="rounded-xl border border-slate-100 px-4 py-3">
                            <div class="text-xl font-semibold text-slate-950">{{ number_format($value, 0, ',', '.') }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Letzte Importe</h2>
                <div class="mt-4 space-y-3">
                    @forelse($recentImports as $import)
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ ($import->import_type ?? 'members') === 'contacts' ? 'Kontakte' : 'Mitglieder' }}</div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ $import->filename ?? 'Importdatei' }} · {{ $import->imported_count ?? 0 }} importiert · {{ $import->skipped_count ?? 0 }} übersprungen
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Noch keine Importe durchgeführt.</div>
                    @endforelse
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Nächste Termine</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($recentEvents as $event)
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $event->title ?? 'Ohne Titel' }}</div>
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ !empty($event->start) ? \Illuminate\Support\Carbon::parse($event->start)->format('d.m.Y H:i') : 'ohne Datum' }}
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">Noch keine Termine erkennbar.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Letzte Dokumente</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($recentDocuments as $document)
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $document->title ?? 'Ohne Titel' }}</div>
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ $document->category ?? 'Dokument' }} · {{ !empty($document->created_at) ? \Illuminate\Support\Carbon::parse($document->created_at)->format('d.m.Y') : 'ohne Datum' }}
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">Noch keine Dokumente vorhanden.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Formulare</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @forelse($recentForms as $form)
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $form->title ?? 'Ohne Titel' }}</div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ $form->status ?? 'Status offen' }} · {{ !empty($form->created_at) ? \Illuminate\Support\Carbon::parse($form->created_at)->format('d.m.Y') : 'ohne Datum' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Noch keine Formulare angelegt.</div>
                    @endforelse
                </div>
            </section>
        </main>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Lizenz</h2>
                <div class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <div class="font-semibold text-slate-950">{{ $tenant->license_mode_label }}</div>
                    <div class="mt-1">
                        @if($tenant->license_expires_at)
                            gültig bis {{ $tenant->license_expires_at->format('d.m.Y') }}
                        @elseif($tenant->trial_ends_at)
                            Testphase bis {{ $tenant->trial_ends_at->format('d.m.Y') }}
                        @else
                            kein Ablaufdatum hinterlegt
                        @endif
                    </div>
                    <div class="mt-1">Letzte Aktivität: {{ $lastActivity ? $lastActivity->diffForHumans() : 'keine Aktivität' }}</div>
                </div>
                <form method="POST" action="{{ route('admin.tenants.license', $tenant) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="text-sm font-medium text-slate-700">Modus</label>
                        <select name="license_mode" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="standard" @selected(($tenant->license_mode ?? 'standard') === 'standard')>Standard</option>
                            <option value="beta" @selected(($tenant->license_mode ?? 'standard') === 'beta')>Pilotlizenz</option>
                            <option value="gifted" @selected(($tenant->license_mode ?? 'standard') === 'gifted')>Freilizenz</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Gültig bis</label>
                        <input type="date"
                               name="license_expires_at"
                               value="{{ optional($tenant->license_expires_at)->format('Y-m-d') }}"
                               class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Lizenz speichern
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Benutzer</h2>
                <div class="mt-4 space-y-4">
                    @forelse($tenant->users as $user)
                        <div>
                            <div class="font-semibold text-slate-900">{{ $user->name ?: 'Ohne Namen' }}</div>
                            <div class="mt-0.5 break-all text-sm text-slate-500">{{ $user->email }}</div>
                            <div class="mt-1 text-xs text-slate-400">
                                {{ $user->roleLabel() }} · {{ $user->email_verified_at ? 'bestätigt' : 'nicht bestätigt' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Keine Benutzer gefunden.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                <h2 class="text-lg font-semibold text-rose-950">Gefahrenzone</h2>
                <p class="mt-2 text-sm leading-6 text-rose-800">
                    Löscht Verein, Benutzer und zugehörige Vereinsdaten dauerhaft. Nur nutzen, wenn eine Registrierung eindeutig falsch ist.
                </p>
                <form method="POST"
                      action="{{ route('admin.tenants.destroy', $tenant) }}"
                      class="mt-4"
                      onsubmit="const confirmation = prompt('Zum Löschen bitte DELETE eingeben.'); if (confirmation !== 'DELETE') { return false; } this.querySelector('input[name=confirmation]').value = confirmation; return confirm('Verein wirklich endgültig löschen?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirmation" value="">
                    <button type="submit" class="w-full rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-800">
                        Verein löschen
                    </button>
                </form>
            </section>
        </aside>
    </div>
</div>
@endsection
