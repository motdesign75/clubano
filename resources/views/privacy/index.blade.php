@extends('layouts.app')

@section('title', 'Datenschutz')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-[2rem] bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-200">Datenschutz & Vertrauen</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal sm:text-4xl">Eure Daten bleiben unter Kontrolle.</h1>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300">
                    Export, Supportfreigabe, Nachweise und Unterauftragsverarbeiter an einem Ort. So kann der Vorstand jederzeit nachvollziehen, was mit den Vereinsdaten passiert.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                @foreach([
                    ['label' => 'Datenexporte', 'value' => $stats['exports_ready'], 'hint' => 'bereit'],
                    ['label' => 'Support', 'value' => $stats['support_active'], 'hint' => 'Freigabe'],
                    ['label' => 'Unterauftrag', 'value' => $stats['subprocessors'], 'hint' => 'STRATO'],
                    ['label' => 'Löschung', 'value' => $stats['retention'], 'hint' => 'nach Vertragsende'],
                ] as $item)
                    <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $item['label'] }}</div>
                        <div class="mt-2 text-2xl font-semibold">{{ $item['value'] }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ $item['hint'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Datenherausgabe</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">Vollständigen Datenexport erstellen</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Clubano erstellt ein ZIP-Paket mit strukturierten CSV-Dateien und vorhandenen Dokumentdateien. Der Download bleibt 14 Tage verfügbar.
                    </p>
                </div>
                <form method="POST" action="{{ route('privacy.exports.store') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Export erstellen
                    </button>
                </form>
            </div>

            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
                <div class="hidden grid-cols-[minmax(0,1fr)_130px_130px_130px] bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 md:grid">
                    <div>Export</div>
                    <div>Status</div>
                    <div>Größe</div>
                    <div>Aktion</div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($exports as $export)
                        <div class="grid gap-3 px-4 py-4 md:grid-cols-[minmax(0,1fr)_130px_130px_130px] md:items-center">
                            <div>
                                <div class="font-semibold text-slate-950">{{ $export->filename ?: 'Datenexport wird vorbereitet' }}</div>
                                <div class="mt-1 text-sm text-slate-500">
                                    Angefordert {{ $export->created_at->format('d.m.Y H:i') }}
                                    @if($export->expires_at)
                                        · verfügbar bis {{ $export->expires_at->format('d.m.Y') }}
                                    @endif
                                </div>
                                @if($export->failure_reason)
                                    <div class="mt-2 text-sm text-rose-700">{{ $export->failure_reason }}</div>
                                @endif
                            </div>
                            <div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $export->status === \App\Models\PrivacyDataExport::STATUS_READY ? 'bg-emerald-50 text-emerald-700' : ($export->status === \App\Models\PrivacyDataExport::STATUS_FAILED ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                    {{ $export->status_label }}
                                </span>
                            </div>
                            <div class="text-sm font-medium text-slate-600">{{ $export->human_size }}</div>
                            <div>
                                @if($export->status === \App\Models\PrivacyDataExport::STATUS_READY && $export->expires_at?->isFuture())
                                    <a href="{{ route('privacy.exports.download', $export) }}" class="inline-flex rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Download
                                    </a>
                                @else
                                    <span class="text-sm text-slate-400">Nicht verfügbar</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-sm text-slate-500">Noch kein Datenexport erstellt.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Supportfreigabe</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Zeitlich begrenzt helfen lassen</h2>

                @if($activeSupportGrant)
                    <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                        <div class="text-sm font-semibold text-emerald-900">Support ist freigegeben</div>
                        <div class="mt-1 text-sm text-emerald-800">
                            {{ $activeSupportGrant->scope_label }} bis {{ $activeSupportGrant->expires_at->format('d.m.Y H:i') }}.
                        </div>
                        <form method="POST" action="{{ route('privacy.support-grants.revoke', $activeSupportGrant) }}" class="mt-4">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="rounded-xl bg-white px-3 py-2 text-sm font-semibold text-emerald-800 shadow-sm ring-1 ring-emerald-200 transition hover:bg-emerald-100">
                                Freigabe beenden
                            </button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('privacy.support-grants.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label for="scope" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Umfang</label>
                            <select id="scope" name="scope" class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                                <option value="metadata">Metadaten und Einstellungen</option>
                                <option value="documents">Dokumente und Metadaten</option>
                                <option value="finance">Finanzen und Metadaten</option>
                                <option value="full">Erweiterter Support</option>
                            </select>
                        </div>
                        <div>
                            <label for="duration" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Dauer</label>
                            <select id="duration" name="duration" class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                                <option value="2">2 Stunden</option>
                                <option value="24">24 Stunden</option>
                                <option value="168">7 Tage</option>
                            </select>
                        </div>
                        <div>
                            <label for="reason" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Grund</label>
                            <textarea id="reason" name="reason" rows="3" class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" placeholder="z. B. Hilfe beim Import oder bei Berechtigungen"></textarea>
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                            Support freigeben
                        </button>
                    </form>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Nachweise</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Was Clubano dokumentiert</h2>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                        <div class="font-semibold text-slate-900">AVV & TOM</div>
                        <div class="mt-1">Auftragsverarbeitung, technische und organisatorische Maßnahmen.</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                        <div class="font-semibold text-slate-900">Unterauftragsverarbeiter</div>
                        <div class="mt-1">STRATO GmbH, Serverinfrastruktur in Deutschland/EU-EWR-Kontext.</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                        <div class="font-semibold text-slate-900">Löschfristen</div>
                        <div class="mt-1">Produktivdaten nach Vertragsende grundsätzlich nach drei Monaten.</div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Freigabehistorie</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Supportzugriffe nachvollziehen</h2>
            </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($supportGrants as $grant)
                <div class="rounded-2xl border border-slate-200 px-4 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-950">{{ $grant->scope_label }}</div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ $grant->created_at->format('d.m.Y H:i') }} bis {{ $grant->expires_at->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $grant->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $grant->isActive() ? 'aktiv' : 'beendet' }}
                        </span>
                    </div>
                    @if($grant->reason)
                        <div class="mt-3 text-sm leading-6 text-slate-600">{{ $grant->reason }}</div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl bg-slate-50 px-4 py-6 text-sm text-slate-500">Noch keine Supportfreigabe erteilt.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
