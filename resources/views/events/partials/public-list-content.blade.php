<div class="{{ $isEmbed ? 'min-h-screen bg-[#f4f0e8]' : 'bg-slate-50' }}">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                    {{ $tenant->name }}
                </div>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Kommende Veranstaltungen</h1>
                <p class="mt-3 max-w-2xl text-sm text-slate-600 sm:text-base">
                    Eine übersichtliche Liste aller öffentlichen Termine mit Kategorie-Filter, Preisen und direktem Weg zur Detailseite.
                </p>
            </div>

            <form method="GET" action="{{ $isEmbed ? $embedUrl : $publicListUrl }}" class="w-full max-w-md">
                <label for="category" class="mb-2 block text-sm font-medium text-slate-700">Nach einer Kategorie suchen</label>
                <select id="category" name="category" onchange="this.form.submit()"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Alle Kategorien</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->slug }}" @selected($selectedCategorySlug === $category->slug)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="space-y-10">
            @forelse($groupedEvents as $month => $monthEvents)
                <section>
                    <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">{{ \Illuminate\Support\Str::headline($month) }}</h2>
                    <div class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-3xl border border-slate-200 bg-white/80 shadow-sm backdrop-blur">
                        @foreach($monthEvents as $event)
                            <a href="{{ route('events.public.show', $event->id) }}"
                               @if($isEmbed) target="_blank" rel="noopener noreferrer" @endif
                               class="group grid gap-4 px-4 py-5 transition hover:bg-white sm:grid-cols-[110px_180px_minmax(0,1fr)_220px_28px] sm:items-center sm:px-6">
                                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                    <div class="h-5 w-full" style="background-color: {{ $event->date_accent_color }}"></div>
                                    <div class="px-3 py-4 text-center">
                                        <div class="text-4xl font-bold leading-none text-slate-900 sm:text-5xl">{{ $event->start->format('d') }}</div>
                                        <div class="mt-1 text-xl font-medium text-slate-500 sm:text-2xl">{{ $event->start->translatedFormat('M.') }}</div>
                                    </div>
                                </div>

                                <div class="overflow-hidden rounded-2xl bg-slate-100 shadow-sm">
                                    @if($event->image_url)
                                        <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="h-28 w-full object-cover sm:h-24">
                                    @else
                                        <div class="flex h-28 items-center justify-center bg-slate-200 text-sm font-medium text-slate-500 sm:h-24">
                                            Kein Bild
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    @if($event->category)
                                        <div class="mb-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700" style="background-color: {{ $event->category->color }}20;">
                                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $event->category->color }}"></span>
                                            {{ $event->category->name }}
                                        </div>
                                    @endif

                                    <h3 class="text-xl font-bold text-slate-900 transition group-hover:text-blue-700 sm:text-2xl">
                                        {{ $event->title }}
                                    </h3>

                                    <p class="mt-1 text-base text-slate-700 sm:text-lg">
                                        {{ $event->start->translatedFormat('D., d. F Y H:i') }}
                                    </p>

                                    <p class="mt-3 flex items-center gap-2 text-base text-slate-700 sm:text-lg">
                                        <span class="text-slate-900">●</span>
                                        <span>{{ $event->short_location }}</span>
                                    </p>
                                </div>

                                <div class="flex flex-col items-start gap-3 sm:items-end">
                                    <span class="inline-flex rounded-full px-4 py-2 text-sm font-semibold {{ $event->booking_enabled && $event->activeBookingForm ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $event->booking_enabled && $event->activeBookingForm ? 'Anmeldung geöffnet' : 'Details verfügbar' }}
                                    </span>

                                    <div class="text-left sm:text-right">
                                        <div class="text-2xl font-bold text-slate-900 sm:text-3xl">{{ $event->price_label }}</div>
                                        @if($event->is_paid)
                                            <div class="mt-1 text-sm text-slate-500">pro Person</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="hidden text-4xl text-slate-300 transition group-hover:text-slate-500 sm:block">
                                    ›
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-3xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
                    <h2 class="text-2xl font-semibold text-slate-900">Keine passenden Veranstaltungen gefunden</h2>
                    <p class="mt-3 text-slate-600">Für diese Auswahl sind aktuell keine öffentlichen Termine hinterlegt.</p>
                </div>
            @endforelse
        </div>

        @if($events->hasPages())
            <div class="mt-10 flex justify-center">
                <a href="{{ $events->nextPageUrl() ?: '#' }}"
                   @class([
                       'inline-flex w-full items-center justify-center rounded-2xl border px-6 py-3 text-base font-medium shadow-sm transition sm:min-w-[260px] sm:w-auto sm:py-4 sm:text-lg',
                       'border-slate-300 bg-white text-slate-900 hover:bg-slate-50' => $events->hasMorePages(),
                       'pointer-events-none border-slate-200 bg-slate-100 text-slate-400' => ! $events->hasMorePages(),
                   ])>
                    {{ $events->hasMorePages() ? 'Mehr laden' : 'Keine weiteren Veranstaltungen' }}
                </a>
            </div>
        @endif
    </div>
</div>
