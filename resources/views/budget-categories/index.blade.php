@extends('layouts.app')

@section('title', 'Haushaltsbereiche')

@section('content')
<div class="mx-auto max-w-6xl space-y-8">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300">Finanzen</p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Haushaltsbereiche</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        Bereiche verbinden Konten, Planpositionen und Ist-Buchungen. So erkennst du spaeter schnell, wo ein Ueberschuss entsteht und wo ein Defizit droht.
                    </p>
                </div>
            </div>

            <a href="{{ route('budgets.index') }}" class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                Zum Haushaltsplan
            </a>
        </div>

        @if(session('success'))
            <div class="border-t border-emerald-200 bg-emerald-50 px-6 py-4 text-sm font-semibold text-emerald-800 md:px-8">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="border-t border-rose-200 bg-rose-50 px-6 py-4 text-sm font-semibold text-rose-800 md:px-8">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="border-t border-rose-200 bg-rose-50 px-6 py-4 text-sm text-rose-800 md:px-8">
                <div class="font-semibold">Bitte pruefe die Angaben.</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Bereiche verwalten</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Deaktivierte Bereiche bleiben an alten Plaenen erhalten, werden aber nicht mehr neu angeboten.</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-3 text-right">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Aktiv</div>
                    <div class="mt-1 text-lg font-semibold text-slate-950">{{ $categories->where('active', true)->count() }}</div>
                </div>
            </div>

            <div class="mt-5 space-y-3">
                @forelse($categories as $category)
                    <form method="POST" action="{{ route('budget-categories.update', $category) }}" class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_9rem_8rem_auto] lg:items-end">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Name</label>
                                <input type="text" name="name" value="{{ old('name', $category->name) }}"
                                       class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Farbe</label>
                                <select name="color" class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                                    @foreach(['blue', 'emerald', 'amber', 'rose', 'purple', 'cyan', 'lime', 'indigo', 'slate', 'zinc'] as $color)
                                        <option value="{{ $color }}" @selected(old('color', $category->color) === $color)>{{ ucfirst($color) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Sortierung</label>
                                <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" max="999"
                                       class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox" name="active" value="1" class="rounded border-slate-300 text-slate-950" @checked(old('active', $category->active))>
                                    Aktiv
                                </label>
                                <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                    Speichern
                                </button>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                            <div>{{ $category->accounts_count }} Konten · {{ $category->budget_items_count }} Planpositionen</div>
                            @if($category->accounts_count === 0 && $category->budget_items_count === 0)
                                <button type="submit"
                                        form="delete-category-{{ $category->id }}"
                                        class="font-semibold text-rose-700 hover:text-rose-800"
                                        onclick="return confirm('Diesen Haushaltsbereich wirklich loeschen?');">
                                    Loeschen
                                </button>
                            @endif
                        </div>
                    </form>

                    @if($category->accounts_count === 0 && $category->budget_items_count === 0)
                        <form id="delete-category-{{ $category->id }}" method="POST" action="{{ route('budget-categories.destroy', $category) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-sm text-slate-500">
                        Noch keine Haushaltsbereiche vorhanden.
                    </div>
                @endforelse
            </div>
        </div>

        <aside class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold text-slate-950">Neuen Bereich anlegen</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Nutze Bereiche wie Veranstaltungen, Jugend, Verwaltung oder Vereinsheim. Die Konten bleiben weiterhin die buchhalterische Grundlage.</p>

            <form method="POST" action="{{ route('budget-categories.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                           placeholder="z. B. Veranstaltungen" required>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Farbe</label>
                        <select name="color" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                            @foreach(['blue', 'emerald', 'amber', 'rose', 'purple', 'cyan', 'lime', 'indigo', 'slate', 'zinc'] as $color)
                                <option value="{{ $color }}" @selected(old('color', 'blue') === $color)>{{ ucfirst($color) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Sortierung</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', 100) }}" min="0" max="999"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="active" value="1" class="rounded border-slate-300 text-slate-950" checked>
                    Aktiv im Haushaltsplan anbieten
                </label>

                <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Bereich anlegen
                </button>
            </form>
        </aside>
    </section>
</div>
@endsection
