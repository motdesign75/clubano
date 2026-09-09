@php
    $lastTotal = collect($totalMembers)->last() ?? 0;
    $firstTotal = collect($totalMembers)->first() ?? 0;
    $netGrowth = $lastTotal - $firstTotal;
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

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 font-medium text-emerald-800 ring-1 ring-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Eintritte
                </span>
                <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 font-medium text-rose-800 ring-1 ring-rose-200">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    Austritte
                </span>
            </div>
        </div>

        <div class="mt-6 h-[260px] sm:h-[300px]">
            <canvas id="memberBarChart" class="h-full w-full"></canvas>
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

        <div class="mt-6 h-[260px] sm:h-[300px]">
            <canvas id="memberLineChart" class="h-full w-full"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let barChartInstance = null;
        let lineChartInstance = null;

        function clubanoNumber(value) {
            return new Intl.NumberFormat('de-DE').format(value);
        }

        function renderMemberCharts() {
            const barCanvas = document.getElementById('memberBarChart');
            const lineCanvas = document.getElementById('memberLineChart');

            if (!barCanvas || !lineCanvas || typeof Chart === 'undefined') {
                return;
            }

            const barCtx = barCanvas.getContext('2d');
            const lineCtx = lineCanvas.getContext('2d');

            if (barChartInstance) barChartInstance.destroy();
            if (lineChartInstance) lineChartInstance.destroy();

            const gridColor = 'rgba(148, 163, 184, 0.18)';
            const tickColor = '#64748b';
            const titleColor = '#0f172a';

            const entriesGradient = barCtx.createLinearGradient(0, 0, 0, 280);
            entriesGradient.addColorStop(0, 'rgba(16, 185, 129, 0.95)');
            entriesGradient.addColorStop(1, 'rgba(16, 185, 129, 0.25)');

            const exitsGradient = barCtx.createLinearGradient(0, 0, 0, 280);
            exitsGradient.addColorStop(0, 'rgba(244, 63, 94, 0.95)');
            exitsGradient.addColorStop(1, 'rgba(244, 63, 94, 0.22)');

            barChartInstance = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: @json($months),
                    datasets: [
                        {
                            label: 'Eintritte',
                            data: @json($entries),
                            backgroundColor: entriesGradient,
                            borderRadius: 10,
                            borderSkipped: false,
                            maxBarThickness: 28,
                        },
                        {
                            label: 'Austritte',
                            data: @json($exits),
                            backgroundColor: exitsGradient,
                            borderRadius: 10,
                            borderSkipped: false,
                            maxBarThickness: 28,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.94)',
                            titleColor: '#fff',
                            bodyColor: '#e2e8f0',
                            padding: 14,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${clubanoNumber(context.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, stepSize: 1, color: tickColor },
                            grid: { color: gridColor, drawBorder: false }
                        },
                        x: {
                            ticks: { color: tickColor },
                            grid: { display: false, drawBorder: false }
                        }
                    }
                }
            });

            const lineGradient = lineCtx.createLinearGradient(0, 0, 0, 300);
            lineGradient.addColorStop(0, 'rgba(79, 70, 229, 0.30)');
            lineGradient.addColorStop(1, 'rgba(79, 70, 229, 0.02)');

            lineChartInstance = new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: @json($months),
                    datasets: [{
                        label: 'Mitglieder gesamt',
                        data: @json($totalMembers),
                        borderColor: '#312e81',
                        borderWidth: 3,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#312e81',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        fill: true,
                        backgroundColor: lineGradient,
                        tension: 0.38
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.94)',
                            titleColor: '#fff',
                            bodyColor: '#e2e8f0',
                            padding: 14,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Mitglieder: ${clubanoNumber(context.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: { stepSize: 1, color: tickColor },
                            grid: { color: gridColor, drawBorder: false }
                        },
                        x: {
                            ticks: { color: tickColor },
                            grid: { display: false, drawBorder: false }
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(renderMemberCharts, 120);
        });

        if (window.Livewire) {
            Livewire.hook('message.processed', () => {
                setTimeout(renderMemberCharts, 120);
            });
        }
    </script>
</div>
