@php
    $statusClasses = [
        'manual' => 'bg-slate-100 text-slate-700',
        'invited' => 'bg-amber-100 text-amber-800',
        'active' => 'bg-emerald-100 text-emerald-800',
        'failed' => 'bg-rose-100 text-rose-800',
        'ignored' => 'bg-slate-200 text-slate-700',
        'removed' => 'bg-zinc-100 text-zinc-700',
    ];

    $statusLabels = [
        'manual' => 'Manuell',
        'invited' => 'Eingeladen',
        'active' => 'Aktiviert',
        'failed' => 'Fehlgeschlagen',
        'ignored' => 'Ignoriert',
        'removed' => 'Entfernt',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Kommunikation</div>
                <h1 class="mt-2 text-3xl font-semibold text-slate-900">App-Synchronisierung</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Mitglieder werden als getrennte App-Zugänge eingeladen. Diese Zugänge gelten nur für Mein Clubano und nicht für die Verwaltungsanwendung.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-5">
            @foreach([
                'synced' => ['label' => 'Synchronisiert', 'value' => $stats['synced'], 'tone' => 'bg-blue-50 text-blue-900'],
                'activated' => ['label' => 'Aktiviert', 'value' => $stats['activated'], 'tone' => 'bg-emerald-50 text-emerald-900'],
                'failed' => ['label' => 'Fehlgeschlagen', 'value' => $stats['failed'], 'tone' => 'bg-rose-50 text-rose-900'],
                'removed' => ['label' => 'Entfernt', 'value' => $stats['removed'], 'tone' => 'bg-zinc-100 text-zinc-800'],
                'ignored' => ['label' => 'Ignoriert', 'value' => $stats['ignored'], 'tone' => 'bg-slate-100 text-slate-800'],
            ] as $card)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-sm font-medium text-slate-500">{{ $card['label'] }}</div>
                    <div class="mt-3 inline-flex min-w-16 items-center justify-center rounded-2xl px-4 py-2 text-2xl font-semibold {{ $card['tone'] }}">
                        {{ $card['value'] }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_0.8fr]">
            <form method="POST" action="{{ route('mobile-app-sync.update') }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-950">Synchronisierung steuern</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Wenn aktiv, werden passende Mitglieder als App-Zugänge vorbereitet. Ohne Tag-Auswahl werden alle aktiven Mitglieder mit E-Mail-Adresse berücksichtigt.
                        </p>
                    </div>

                    <label class="inline-flex items-center gap-3 rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-800">
                        <input type="checkbox" name="mobile_app_sync_enabled" value="1" class="rounded border-slate-300" @checked(old('mobile_app_sync_enabled', $tenant->mobile_app_sync_enabled))>
                        Aktiv
                    </label>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-ui.label for="mobile_app_sync_tag_id">Segment/Tag</x-ui.label>
                        <select id="mobile_app_sync_tag_id" name="mobile_app_sync_tag_id" class="mt-1 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Alle aktiven Mitglieder mit E-Mail</option>
                            @foreach($tags as $tag)
                                <option value="{{ $tag->id }}" @selected((string) old('mobile_app_sync_tag_id', $tenant->mobile_app_sync_tag_id) === (string) $tag->id)>
                                    {{ $tag->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('mobile_app_sync_tag_id')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3">
                        <div class="text-sm font-semibold text-blue-950">{{ $eligibleCount }} Mitglied(er) im aktuellen Segment</div>
                        <p class="mt-1 text-sm leading-6 text-blue-800">
                            Nur Mitglieder mit E-Mail-Adresse werden eingeladen. Doppelte E-Mail-Adressen werden als Fehler markiert.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Einstellungen speichern
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('mobile-app-sync.run') }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-xl font-semibold text-slate-950">Jetzt synchronisieren</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Neue App-Zugänge erhalten eine Einladung per E-Mail. Bestehende aktivierte Zugänge bleiben aktiv.
                </p>

                <label class="mt-6 inline-flex items-center gap-3 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="send_invitations" value="1" class="rounded border-slate-300" checked>
                    Einladungen direkt versenden
                </label>

                <button class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700" @disabled(! $tenant->mobile_app_sync_enabled)>
                    Synchronisierung ausführen
                </button>

                @unless($tenant->mobile_app_sync_enabled)
                    <p class="mt-3 text-sm text-amber-700">Aktiviere die Synchronisierung zuerst.</p>
                @endunless
            </form>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Letzte App-Zugänge</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Mitglied</th>
                            <th class="px-5 py-3">Login</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Einladung</th>
                            <th class="px-5 py-3">Fehler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($appUsers as $appUser)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900">{{ $appUser->member?->full_name ?: 'Ohne Mitglied' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $appUser->member?->member_id }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $appUser->username }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$appUser->sync_status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $statusLabels[$appUser->sync_status] ?? $appUser->sync_status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    @if($appUser->accepted_at)
                                        Aktiviert am {{ $appUser->accepted_at->format('d.m.Y H:i') }}
                                    @elseif($appUser->invitation_sent_at)
                                        Gesendet am {{ $appUser->invitation_sent_at->format('d.m.Y H:i') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $appUser->sync_error ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-slate-500">
                                    Noch keine App-Zugänge vorhanden.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
