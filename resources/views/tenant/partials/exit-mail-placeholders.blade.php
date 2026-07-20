<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
    <div class="text-sm font-semibold text-slate-900">Platzhalter</div>
    <p class="mt-1 text-sm text-slate-600">
        Diese Werte kannst du direkt in Betreff und Text verwenden.
    </p>

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach([
            '{anrede}',
            '{name}',
            '{vorname}',
            '{nachname}',
            '{email}',
            '{mitgliedsnummer}',
            '{mitgliedschaft}',
            '{austrittsdatum}',
            '{kuendigungsdatum}',
            '{verein}',
            '{heute}',
        ] as $placeholder)
            <button type="button"
                    class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-100"
                    onclick="window.insertIntoExitMailEditor?.('{{ $placeholder }}')">
                {{ $placeholder }}
            </button>
        @endforeach
    </div>
</div>
