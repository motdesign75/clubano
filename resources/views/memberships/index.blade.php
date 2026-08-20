@extends('layouts.app')

@section('title', 'Mitgliedschaften verwalten')

@section('content')
    @php
        $labels = [
            'monatlich' => 'monatlich',
            'vierteljährlich' => 'vierteljährlich',
            'halbjährlich' => 'halbjährlich',
            'jährlich' => 'jährlich',
        ];

        $annualFactors = [
            'monatlich' => 12,
            'vierteljährlich' => 4,
            'halbjährlich' => 2,
            'jährlich' => 1,
        ];

        $totalMembers = $memberships->sum('members_count');
        $annualVolume = $memberships->sum(fn ($membership) => ($membership->amount ?? 0) * ($annualFactors[$membership->interval] ?? 1) * ($membership->members_count ?? 0));
        $activeModels = $memberships->where('members_count', '>', 0)->count();
        $membersWithoutMembershipCount = ($membersWithoutMembership ?? collect())->count();
        $billingMembers = $billingMembers ?? collect();
        $dueBillingCount = $dueBillingCount ?? 0;
    @endphp

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-[2rem] bg-slate-950 px-6 py-7 text-white shadow-sm sm:px-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-white/50">Beitragszentrale</div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Mitgliedschaften</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                        Beitragsmodelle bestimmen Beitrag, Rhythmus und eine mögliche einmalige Aufnahmegebühr. Abgerechnet wird zuerst als Entwurf, damit nichts versehentlich rausgeht.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    @if($memberships->isNotEmpty())
                        <form method="POST" action="{{ route('invoices.generateMemberships') }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100 sm:w-auto">
                                Beitragsentwürfe vorbereiten
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('memberships.create') }}" class="inline-flex w-full items-center justify-center rounded-full border border-white/20 bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15 sm:w-auto">
                        Neues Beitragsmodell
                    </a>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($memberships->isEmpty())
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
                <div class="mx-auto max-w-xl">
                    <div class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-400">Startpunkt</div>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Erst das Beitragsmodell, dann die Mitglieder</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Lege zuerst die Modelle an, die der Verein wirklich nutzt: Erwachsene, Familie, Jugend oder Fördermitglied.
                    </p>
                </div>
                <a href="{{ route('memberships.create') }}" class="mt-6 inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                    Erstes Beitragsmodell anlegen
                </a>
            </div>
        @else
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm font-medium text-slate-500">Beitragsmodelle</div>
                    <div class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ $memberships->count() }}</div>
                    <div class="mt-2 text-sm text-slate-500">{{ $activeModels }} davon mit Mitgliedern</div>
                </div>
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm font-medium text-slate-500">Zugeordnete Mitglieder</div>
                    <div class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ $totalMembers }}</div>
                    <div class="mt-2 text-sm text-slate-500">über alle Modelle hinweg</div>
                </div>
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="text-sm font-medium text-slate-500">Jahresvolumen</div>
                    <div class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ number_format($annualVolume, 2, ',', '.') }} €</div>
                    <div class="mt-2 text-sm text-slate-500">hochgerechnet aus Betrag und Intervall</div>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[0.85fr_1.4fr]">
                <div class="rounded-3xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Fehlt noch</div>
                            <h2 class="mt-2 text-xl font-semibold text-amber-950">Mitglieder ohne Beitragsmodell</h2>
                            <p class="mt-2 text-sm leading-6 text-amber-800">
                                Diese Mitglieder werden bei Beitragsentwürfen nicht berücksichtigt.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-white px-4 py-2 text-2xl font-semibold text-amber-900 shadow-sm">
                            {{ $membersWithoutMembershipCount }}
                        </div>
                    </div>

                    <div class="mt-5">
                        @if($membersWithoutMembership->isNotEmpty())
                            <form method="POST" action="{{ route('memberships.member-billing.assign') }}" class="space-y-4">
                                @csrf

                                <div class="grid gap-3">
                                    <div>
                                        <label for="missing-membership-id" class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Beitragsmodell</label>
                                        <select id="missing-membership-id" name="membership_id" class="w-full rounded-2xl border-amber-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-200" required>
                                            <option value="">Modell wählen</option>
                                            @foreach($memberships as $membership)
                                                <option value="{{ $membership->id }}" @selected((string) old('membership_id') === (string) $membership->id)>
                                                    {{ $membership->name }} · {{ number_format($membership->amount ?? 0, 2, ',', '.') }} € / {{ $membership->interval }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('membership_id')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label for="missing-next-invoice-on" class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Nächste Beitragsrechnung</label>
                                        <input id="missing-next-invoice-on" type="date" name="next_membership_invoice_on" value="{{ old('next_membership_invoice_on', now()->toDateString()) }}" class="w-full rounded-2xl border-amber-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-amber-200 bg-white p-3 shadow-sm">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <label class="flex items-center gap-3 text-sm font-semibold text-amber-950">
                                            <input id="membership-missing-check-all" type="checkbox" class="rounded border-amber-300 text-amber-700 focus:ring-amber-300" checked>
                                            Alle angezeigten Mitglieder auswählen
                                        </label>
                                        <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full px-6 text-sm font-semibold shadow-sm transition lg:w-auto" style="background-color: #92400e; color: #ffffff;">
                                            Beitragsmodell jetzt zuweisen
                                        </button>
                                    </div>
                                    <p class="mt-2 text-xs leading-5 text-amber-800">
                                        Erst Beitragsmodell wählen, dann Mitglieder prüfen und zuweisen.
                                    </p>
                                </div>

                                <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
                                    @foreach($membersWithoutMembership as $member)
                                        <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-white px-4 py-3 transition hover:bg-amber-50">
                                            <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="membership-missing-checkbox mt-1 rounded border-amber-300 text-amber-700 focus:ring-amber-300" checked>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-semibold text-slate-950">{{ $member->full_name ?: ($member->organization ?: 'Unbenanntes Mitglied') }}</span>
                                                <span class="mt-1 block text-sm text-slate-500">{{ $member->member_id ?: 'ohne Mitgliedsnummer' }}</span>
                                            </span>
                                            <a href="{{ route('members.edit', $member) }}" class="text-xs font-semibold text-amber-800 hover:underline">Prüfen</a>
                                        </label>
                                    @endforeach
                                </div>
                                @error('member_ids')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror

                                <div class="sticky bottom-4 rounded-2xl border border-amber-200 bg-white/95 p-3 shadow-lg backdrop-blur">
                                    <button type="submit" class="inline-flex w-full min-h-12 items-center justify-center rounded-full px-5 text-sm font-semibold shadow-sm transition" style="background-color: #92400e; color: #ffffff;">
                                        Auswahl zuweisen
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                Alle aktiven Mitglieder haben ein Beitragsmodell.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Beitragsfahrplan</div>
                            <h2 class="mt-2 text-xl font-semibold text-slate-950">Wer ist wann dran?</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $dueBillingCount }} Mitglied{{ $dueBillingCount === 1 ? '' : 'er' }} aktuell fällig.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                                <tr>
                                    <th class="px-5 py-3">Mitglied</th>
                                    <th class="px-5 py-3">Modell</th>
                                    <th class="px-5 py-3">Letzte Rechnung</th>
                                    <th class="px-5 py-3">Nächste Rechnung</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($billingMembers as $row)
                                    @php
                                        $member = $row['member'];
                                        $lastInvoice = $row['last_invoice'];
                                        $nextDate = $row['next_date'];
                                    @endphp
                                    <tr class="{{ $row['is_due'] ? 'bg-amber-50/60' : '' }}">
                                        <td class="px-5 py-4">
                                            <a href="{{ route('members.show', $member) }}" class="font-semibold text-slate-950 hover:underline">
                                                {{ $member->full_name ?: ($member->organization ?: 'Unbenanntes Mitglied') }}
                                            </a>
                                            <div class="mt-1 text-xs text-slate-500">{{ $member->member_id ?: 'ohne Mitgliedsnummer' }}</div>
                                        </td>
                                        <td class="px-5 py-4">
                                            @if($row['membership'])
                                                <div class="font-medium text-slate-900">{{ $row['membership']->name }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ number_format($member->membership_amount ?? $row['membership']->amount ?? 0, 2, ',', '.') }} € / {{ $member->membership_interval ?: $row['membership']->interval }}</div>
                                            @else
                                                <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">fehlt</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            @if($lastInvoice)
                                                <a href="{{ route('invoices.show', $lastInvoice) }}" class="font-medium text-slate-900 hover:underline">{{ $lastInvoice->invoice_number }}</a>
                                                <div class="mt-1 text-xs text-slate-500">{{ optional($lastInvoice->invoice_date)->format('d.m.Y') }}</div>
                                            @else
                                                <span class="text-slate-400">noch keine</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            @if($row['membership'])
                                                <form method="POST" action="{{ route('memberships.member-billing.update', $member) }}" class="flex min-w-[220px] items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="date" name="next_membership_invoice_on" value="{{ old('next_membership_invoice_on', optional($nextDate)->format('Y-m-d')) }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                    <button type="submit" class="rounded-xl bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Speichern</button>
                                                </form>
                                                @if($row['is_due'])
                                                    <div class="mt-2 text-xs font-semibold text-amber-800">jetzt fällig</div>
                                                @endif
                                            @else
                                                <a href="{{ route('members.edit', $member) }}" class="text-sm font-semibold text-amber-800 hover:underline">Modell zuordnen</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-slate-500">Noch keine Mitglieder vorhanden.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Modelle und Abrechnung</h2>
                        <p class="mt-1 text-sm text-slate-500">Clubano erzeugt fehlende Beitragsrechnungen zuerst als Entwurf. Prüfen, dann freigeben.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-white text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Bezeichnung</th>
                                <th class="px-5 py-3">Beitrag</th>
                                <th class="px-5 py-3">Aufnahme</th>
                                <th class="px-5 py-3">Abrechnung</th>
                                <th class="px-5 py-3">Mitglieder</th>
                                <th class="px-5 py-3 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($memberships as $membership)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-slate-950">{{ $membership->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            Snapshot-Basis für neu zugeordnete Mitglieder
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-semibold tabular-nums text-slate-950">{{ number_format($membership->amount ?? 0, 2, ',', '.') }} €</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            {{ number_format(($membership->amount ?? 0) * ($annualFactors[$membership->interval] ?? 1), 2, ',', '.') }} € pro Jahr
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-semibold tabular-nums text-slate-950">{{ number_format($membership->admission_fee ?? 0, 2, ',', '.') }} €</div>
                                        <div class="mt-1 text-xs text-slate-500">einmalig</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ $labels[$membership->interval] ?? ucfirst((string) $membership->interval) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <div class="font-medium text-slate-950">{{ $membership->members_count }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <form method="POST" action="{{ route('invoices.generateMemberships') }}">
                                                @csrf
                                                <input type="hidden" name="membership_id" value="{{ $membership->id }}">
                                                <button type="submit" onclick="return confirm('Für dieses Beitragsmodell jetzt fehlende Beitragsrechnungen als Entwurf vorbereiten?');" class="rounded-full bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                                                    Entwürfe vorbereiten
                                                </button>
                                            </form>
                                            <a href="{{ route('memberships.edit', $membership) }}" class="text-sm font-medium text-slate-700 hover:text-slate-950">
                                                Bearbeiten
                                            </a>
                                            <form action="{{ route('memberships.destroy', $membership) }}" method="POST" onsubmit="return confirm('Diese Mitgliedschaft wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-700">
                                                    Löschen
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkAll = document.getElementById('membership-missing-check-all');
            const checkboxes = [...document.querySelectorAll('.membership-missing-checkbox')];

            if (!checkAll || checkboxes.length === 0) {
                return;
            }

            const refreshCheckAll = () => {
                const checked = checkboxes.filter((checkbox) => checkbox.checked).length;
                checkAll.checked = checked === checkboxes.length;
                checkAll.indeterminate = checked > 0 && checked < checkboxes.length;
            };

            checkAll.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = checkAll.checked;
                });
            });

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshCheckAll));
            refreshCheckAll();
        });
    </script>
@endpush
