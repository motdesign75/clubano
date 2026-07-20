@php
    $lastTotal = collect($totalMembers)->last() ?? 0;
    $firstTotal = collect($totalMembers)->first() ?? 0;
    $netGrowth = $lastTotal - $firstTotal;
@endphp

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-slate-950 px-5 py-4 text-white">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-white/55">Mitglieder gesamt</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight">{{ $lastTotal }}</div>
            <div class="mt-2 text-sm text-white/70">Aktueller Bestand im Verein</div>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Neue Mitglieder</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-emerald-900">{{ $entriesThisYear }}</div>
            <div class="mt-2 text-sm text-emerald-800/80">Eintritte im laufenden Jahr</div>
        </div>

        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Saldo</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight {{ $netGrowth >= 0 ? 'text-emerald-900' : 'text-rose-900' }}">
                {{ $netGrowth >= 0 ? '+' : '' }}{{ $netGrowth }}
            </div>
            <div class="mt-2 text-sm text-slate-600">{{ $exitsLast12Months }} Austritte in den letzten 12 Monaten</div>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Bewegung</div>
                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Mitglieder wachsen oder gehen</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Eine saubere Sicht auf Eintritte und Austritte pro Monat in den letzten 12 Monaten.
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

        <div class="mt-6 h-[320px]">
            <canvas id="memberBarChart" class="h-full w-full"></canvas>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Bestand</div>
                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Mitglieder gesamt</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    So entwickelt sich die Größe des Vereins über das Jahr.
                </p>
            </div>

            <div class="rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Veränderung</div>
                <div class="mt-1 text-lg font-semibold {{ $netGrowth >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $netGrowth >= 0 ? '+' : '' }}{{ $netGrowth }} Mitglieder
                </div>
            </div>
        </div>

        <div class="mt-6 h-[320px]">
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
