@php
    $lastTotal = collect($totalMembers)->last() ?? 0;
    $firstTotal = collect($totalMembers)->first() ?? 0;
    $netGrowth = $lastTotal - $firstTotal;
    $entrySeries = collect($entries);
    $exitSeries = collect($exits);
    $totalSeries = collect($totalMembers);
    $movementMax = max(1, (int) $entrySeries->merge($exitSeries)->max());
    $totalMin = (int) $totalSeries->min();
    $totalMax = (int) $totalSeries->max();
    $totalRange = max(1, $totalMax - $totalMin);
    $pointCount = max(1, $totalSeries->count() - 1);
    $sparkPoints = $totalSeries
        ->values()
        ->map(function ($value, $index) use ($pointCount, $totalMin, $totalRange) {
            $x = $pointCount === 0 ? 50 : round(($index / $pointCount) * 100, 2);
            $y = round(88 - (((int) $value - $totalMin) / $totalRange * 68), 2);

            return $x . ',' . $y;
        })
        ->implode(' ');
    $areaPoints = $sparkPoints ? '0,100 ' . $sparkPoints . ' 100,100' : '';
@endphp

<div class="space-y-5">
    <div class="rounded-2xl bg-slate-950 p-5 text-white shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Entwicklung</div>
                <h3 class="mt-2 text-2xl font-semibold tracking-tight">Wie steht der Verein da?</h3>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                    Mitgliederbestand, neue Eintritte und Austritte auf einen Blick.
                </p>
            </div>

            <div class="grid gap-2 sm:grid-cols-3 lg:min-w-[520px]">
                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Bestand</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $lastTotal }}</div>
                    <div class="mt-1 text-xs text-slate-300">aktive Mitglieder</div>
                </div>
                <div class="rounded-xl bg-emerald-400/10 px-4 py-3 ring-1 ring-emerald-300/25">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-200">Eintritte</div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-100">{{ $entriesThisYear }}</div>
                    <div class="mt-1 text-xs text-emerald-100/75">im Jahr {{ now()->year }}</div>
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Saldo</div>
                    <div class="mt-2 text-2xl font-semibold {{ $netGrowth >= 0 ? 'text-emerald-100' : 'text-rose-100' }}">
                        {{ $netGrowth >= 0 ? '+' : '' }}{{ $netGrowth }}
                    </div>
                    <div class="mt-1 text-xs text-slate-300">{{ $exitsLast12Months }} Austritte in 12 Monaten</div>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Bewegung</div>
                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Eintritte und Austritte</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Monat für Monat: Was kam dazu, was ist weggefallen?
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-800 ring-1 ring-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Eintritte
                </span>
                <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 text-rose-800 ring-1 ring-rose-200">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    Austritte
                </span>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach($months as $index => $month)
                @php
                    $entryValue = (int) ($entries[$index] ?? 0);
                    $exitValue = (int) ($exits[$index] ?? 0);
                    $entryHeight = max($entryValue > 0 ? 14 : 3, round(($entryValue / $movementMax) * 56));
                    $exitHeight = max($exitValue > 0 ? 14 : 3, round(($exitValue / $movementMax) * 56));
                    $hasMovement = $entryValue > 0 || $exitValue > 0;
                @endphp
                <div class="rounded-xl border {{ $hasMovement ? 'border-slate-200 bg-slate-50' : 'border-slate-100 bg-white' }} p-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-semibold text-slate-900">{{ $month }}</div>
                        @if($hasMovement)
                            <div class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                {{ $entryValue - $exitValue >= 0 ? '+' : '' }}{{ $entryValue - $exitValue }}
                            </div>
                        @else
                            <div class="text-xs text-slate-400">ruhig</div>
                        @endif
                    </div>

                    <div class="mt-4 flex h-20 items-end justify-center gap-2 rounded-lg bg-white px-3 py-2 ring-1 ring-slate-100">
                        <div class="flex h-full flex-1 items-end justify-center">
                            <div class="w-full max-w-8 rounded-t-lg bg-emerald-500/80" style="height: {{ $entryHeight }}px"></div>
                        </div>
                        <div class="flex h-full flex-1 items-end justify-center">
                            <div class="w-full max-w-8 rounded-t-lg bg-rose-400/80" style="height: {{ $exitHeight }}px"></div>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-emerald-50 px-2 py-1 font-semibold text-emerald-800">{{ $entryValue }} rein</div>
                        <div class="rounded-lg bg-rose-50 px-2 py-1 font-semibold text-rose-800">{{ $exitValue }} raus</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Bestand</div>
                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Aktiver Mitgliederbestand</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Archivierte Mitglieder sind hier bewusst nicht enthalten.
                </p>
            </div>

            <div class="rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Veränderung</div>
                <div class="mt-1 text-lg font-semibold {{ $netGrowth >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $netGrowth >= 0 ? '+' : '' }}{{ $netGrowth }} Mitglieder
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-100">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-56 w-full overflow-visible">
                <polygon points="{{ $areaPoints }}" fill="rgba(15, 118, 110, 0.12)"></polygon>
                <polyline points="{{ $sparkPoints }}" fill="none" stroke="#0f766e" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>
                @foreach($totalSeries->values() as $index => $value)
                    @php
                        $x = $pointCount === 0 ? 50 : round(($index / $pointCount) * 100, 2);
                        $y = round(88 - (((int) $value - $totalMin) / $totalRange * 68), 2);
                    @endphp
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="1.5" fill="#0f766e" vector-effect="non-scaling-stroke"></circle>
                @endforeach
            </svg>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Start</div>
                    <div class="mt-1 text-lg font-semibold text-slate-950">{{ $firstTotal }}</div>
                </div>
                <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Heute</div>
                    <div class="mt-1 text-lg font-semibold text-slate-950">{{ $lastTotal }}</div>
                </div>
                <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Minimum</div>
                    <div class="mt-1 text-lg font-semibold text-slate-950">{{ $totalMin }}</div>
                </div>
                <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Maximum</div>
                    <div class="mt-1 text-lg font-semibold text-slate-950">{{ $totalMax }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
