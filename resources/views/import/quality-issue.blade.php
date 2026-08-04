@extends('layouts.app')

@section('title', $check['label'] . ' prüfen')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ route('import.report', $importRun) }}" class="text-sm font-semibold text-slate-300 hover:text-white">Zurück zum Importbericht</a>
                <div class="mt-3 text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Korrekturliste</div>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $check['label'] }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">{{ $check['description'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('import.quality-issue.export', [$importRun, $check['key'], 'q' => $search]) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                    Excel herunterladen
                </a>
                <div class="inline-flex min-h-11 items-center rounded-lg bg-white/10 px-4 text-sm font-semibold text-white">
                    {{ $records->total() }} offen
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-950">{{ $check['action'] }}</h2>
            <p class="mt-1 text-sm text-slate-500">Öffne die Datensätze nacheinander und ergänze die fehlenden Angaben.</p>
        </div>

        <form method="GET" action="{{ route('import.quality-issue', [$importRun, $check['key']]) }}" class="border-b border-slate-100 px-5 py-4">
            <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                <div>
                    <label for="q" class="text-sm font-semibold text-slate-900">Korrekturliste durchsuchen</label>
                    <input
                        type="search"
                        name="q"
                        id="q"
                        value="{{ $search }}"
                        placeholder="{{ $importRun->import_type === 'members' ? 'Name, E-Mail, Mitgliedsnummer oder Ort' : 'Name, Organisation, E-Mail oder Ort' }}"
                        class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:ring-slate-300"
                    >
                </div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Suchen
                </button>
                @if($search !== '')
                    <a href="{{ route('import.quality-issue', [$importRun, $check['key']]) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Zurücksetzen
                    </a>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Datensatz</th>
                        <th class="px-5 py-3">Kontakt</th>
                        <th class="px-5 py-3">Details</th>
                        <th class="px-5 py-3 text-right">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($records as $record)
                        @if($importRun->import_type === 'members')
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-950">{{ $record->full_name ?: 'Mitglied #' . $record->id }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $record->member_id ?: 'Keine Mitgliedsnummer' }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <div>{{ $record->email ?: 'Keine E-Mail' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $record->city ?: 'Kein Ort' }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <div>{{ $record->membership?->name ?: 'Keine Mitgliedschaft' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $record->membership_amount ? number_format((float) $record->membership_amount, 2, ',', '.') . ' EUR' : 'Kein Beitrag' }}
                                        @if($record->payment_method)
                                            · {{ $record->paymentMethodLabel() }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('members.edit', $record) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white hover:bg-slate-800">
                                        Bearbeiten
                                    </a>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-950">{{ $record->display_name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $record->contact_type === 'organization' ? 'Organisation' : 'Person' }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <div>{{ $record->email ?: 'Keine E-Mail' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $record->phone ?: $record->mobile ?: 'Keine Telefonnummer' }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    <div>{{ $record->category ?: 'Keine Kategorie' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $record->city ?: 'Kein Ort' }}</div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('contacts.edit', $record) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white hover:bg-slate-800">
                                        Bearbeiten
                                    </a>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-slate-500">Keine offenen Datensätze gefunden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $records->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
