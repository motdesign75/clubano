@extends('layouts.app')

@section('content')
<div class="space-y-6 p-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Protokoll</div>
            <h1 class="mt-2 text-3xl font-semibold text-slate-900">Versand- & Druckhistorie</h1>
            <p class="mt-2 text-sm text-slate-500">
                Hier seht ihr, welche Vorlagen per Mail versendet oder als Brief-PDF erzeugt wurden.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('mail.create') }}"
               class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Mailversand öffnen
            </a>
            <a href="{{ route('letters.create') }}"
               class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Brief erstellen
            </a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Gesamt</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['total'] }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Mails</div>
            <div class="mt-3 text-3xl font-semibold text-emerald-700">{{ $stats['mail'] }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Briefe</div>
            <div class="mt-3 text-3xl font-semibold text-amber-700">{{ $stats['letter'] }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Heute</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['today'] }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Geoeffnet</div>
            <div class="mt-3 text-3xl font-semibold text-blue-700">{{ $stats['opened'] }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Geklickt</div>
            <div class="mt-3 text-3xl font-semibold text-violet-700">{{ $stats['clicked'] }}</div>
        </div>
    </div>

    <form method="GET" class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-[1.2fr_0.7fr_0.8fr_auto]">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Suche</label>
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Empfänger, Referenz oder Betreff"
                   class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Kanal</label>
            <select name="channel"
                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">Alle</option>
                <option value="mail" @selected(request('channel') === 'mail')>Mail</option>
                <option value="letter" @selected(request('channel') === 'letter')>Brief</option>
            </select>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Vorlage</label>
            <select name="template_id"
                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">Alle</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}" @selected((string) request('template_id') === (string) $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-3">
            <button type="submit"
                    class="rounded-full bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                Filtern
            </button>
            <a href="{{ route('templates.dispatch-log') }}"
               class="rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                Zurücksetzen
            </a>
        </div>
    </form>

    <div class="space-y-4 lg:hidden">
        @forelse($logs as $log)
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $log->channel === 'mail' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $log->channel === 'mail' ? 'Mail' : 'Brief' }}
                            </span>
                            <span class="text-xs font-medium text-slate-500">{{ ucfirst($log->recipient_type) }}</span>
                        </div>
                        <h2 class="mt-3 text-base font-semibold text-slate-900">{{ $log->recipient_name }}</h2>
                        @if($log->recipient_reference)
                            <p class="mt-1 text-sm text-slate-500">{{ $log->recipient_reference }}</p>
                        @endif
                    </div>

                    <div class="shrink-0 text-right text-xs text-slate-500">
                        <div class="font-medium text-slate-900">{{ optional($log->dispatched_at)->format('d.m.Y') ?: '–' }}</div>
                        <div class="mt-1">{{ optional($log->dispatched_at)->format('H:i') ?: '' }}</div>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Vorlage</div>
                        <div class="mt-1 text-sm font-medium text-slate-900">{{ $log->template?->name ?? 'Ohne Vorlage' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Aktion</div>
                        <div class="mt-1 text-sm font-medium text-slate-900">{{ $log->subject ?: 'Ohne Betreff' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $log->channel === 'mail' ? 'Versendet' : 'PDF erzeugt' }}</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Tracking</div>
                        @if($log->channel === 'mail')
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $log->open_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $log->open_count > 0 ? $log->open_count . 'x geoeffnet' : 'nicht geoeffnet' }}
                                </span>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $log->click_count > 0 ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $log->click_count > 0 ? $log->click_count . 'x geklickt' : 'kein Klick' }}
                                </span>
                            </div>
                            @if($log->last_opened_at || $log->last_clicked_at)
                                <div class="mt-2 space-y-1 text-xs text-slate-500">
                                    @if($log->last_opened_at)
                                        <div>Zuletzt geoeffnet: {{ $log->last_opened_at->format('d.m.Y H:i') }}</div>
                                    @endif
                                    @if($log->last_clicked_at)
                                        <div>Zuletzt geklickt: {{ $log->last_clicked_at->format('d.m.Y H:i') }}</div>
                                    @endif
                                </div>
                            @endif
                        @else
                            <div class="mt-1 text-sm text-slate-400">Nur fuer Mail</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Erfasst von</div>
                        <div class="mt-1 text-sm font-medium text-slate-900">{{ $log->creator?->name ?? 'System' }}</div>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-slate-200 bg-white p-6 text-center text-slate-500 shadow-sm">
                Noch keine Versand- oder Druckvorgänge protokolliert.
            </div>
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:block">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                <tr>
                    <th class="p-3 text-left">Zeitpunkt</th>
                    <th class="p-3 text-left">Kanal</th>
                    <th class="p-3 text-left">Vorlage</th>
                    <th class="p-3 text-left">Empfaenger</th>
                    <th class="p-3 text-left">Betreff / Aktion</th>
                    <th class="p-3 text-left">Tracking</th>
                    <th class="p-3 text-left">Erfasst von</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-t border-slate-100 align-top">
                        <td class="p-3 text-slate-600">
                            <div class="font-medium text-slate-900">{{ optional($log->dispatched_at)->format('d.m.Y') ?: '–' }}</div>
                            <div class="text-xs">{{ optional($log->dispatched_at)->format('H:i') ?: '' }}</div>
                        </td>
                        <td class="p-3">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $log->channel === 'mail' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $log->channel === 'mail' ? 'Mail' : 'Brief' }}
                            </span>
                        </td>
                        <td class="p-3 text-slate-700">
                            <div class="font-medium text-slate-900">{{ $log->template?->name ?? 'Ohne Vorlage' }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst($log->recipient_type) }}</div>
                        </td>
                        <td class="p-3 text-slate-700">
                            <div class="font-medium text-slate-900">{{ $log->recipient_name }}</div>
                            @if($log->recipient_reference)
                                <div class="text-xs text-slate-500">{{ $log->recipient_reference }}</div>
                            @endif
                        </td>
                        <td class="p-3 text-slate-700">
                            <div class="font-medium text-slate-900">{{ $log->subject ?: 'Ohne Betreff' }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $log->channel === 'mail' ? 'Versendet' : 'PDF erzeugt' }}
                            </div>
                        </td>
                        <td class="p-3 text-slate-700">
                            @if($log->channel === 'mail')
                                <div class="flex flex-col gap-2">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $log->open_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $log->open_count > 0 ? $log->open_count . 'x geoeffnet' : 'nicht geoeffnet' }}
                                        </span>
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $log->click_count > 0 ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $log->click_count > 0 ? $log->click_count . 'x geklickt' : 'kein Klick' }}
                                        </span>
                                    </div>
                                    @if($log->last_opened_at || $log->last_clicked_at)
                                        <div class="space-y-1 text-xs text-slate-500">
                                            @if($log->last_opened_at)
                                                <div>Zuletzt geoeffnet: {{ $log->last_opened_at->format('d.m.Y H:i') }}</div>
                                            @endif
                                            @if($log->last_clicked_at)
                                                <div>Zuletzt geklickt: {{ $log->last_clicked_at->format('d.m.Y H:i') }}</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-slate-400">Nur fuer Mail</span>
                            @endif
                        </td>
                        <td class="p-3 text-slate-600">{{ $log->creator?->name ?? 'System' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-slate-500">Noch keine Versand- oder Druckvorgaenge protokolliert.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
