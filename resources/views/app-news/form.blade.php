@php
    $publishedValue = old('published_at', $item->published_at?->format('Y-m-d\TH:i'));
@endphp

@if($errors->any())
    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
        <div class="font-semibold">Bitte prüfe die Eingaben.</div>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-5 sm:px-6">
        <div class="grid gap-5">
            <div>
                <label for="title" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Titel</label>
                <input id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="160"
                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                       placeholder="Zum Beispiel: Neue Trainingszeiten ab Oktober">
            </div>

            <div>
                <label for="teaser" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Kurztext</label>
                <input id="teaser" name="teaser" value="{{ old('teaser', $item->teaser) }}" maxlength="255"
                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                       placeholder="Ein Satz, der in der App direkt sichtbar ist.">
            </div>

            <div>
                <label for="body" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Nachricht</label>
                <textarea id="body" name="body" rows="8"
                          class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                          placeholder="Die eigentliche Mitteilung für die Mitglieder.">{{ old('body', $item->body) }}</textarea>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-5 sm:px-6">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</label>
                <select id="status" name="status" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs leading-5 text-slate-500">Nur veröffentlichte App-News erscheinen in Mein Clubano.</p>
            </div>

            <div>
                <label for="published_at" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Veröffentlichen ab</label>
                <input id="published_at" name="published_at" type="datetime-local" value="{{ $publishedValue }}"
                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                <p class="mt-2 text-xs leading-5 text-slate-500">Leer lassen, wenn beim Veröffentlichen sofort die aktuelle Zeit gesetzt werden soll.</p>
            </div>
        </div>

        <label class="mt-5 flex gap-3 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3">
            <input type="checkbox" name="push_enabled" value="1" class="mt-1 rounded border-blue-300 text-blue-700 focus:ring-blue-500" @checked(old('push_enabled', $item->push_enabled ?? true))>
            <span>
                <span class="block text-sm font-semibold text-blue-950">Push-Benachrichtigung vormerken</span>
                <span class="mt-1 block text-xs leading-5 text-blue-800">Die Nachricht ist dafür vorbereitet, sobald Push-Tokens und Versanddienst in der App aktiv sind.</span>
            </span>
        </label>
    </section>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('app-news.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Abbrechen
        </a>
    </div>
</form>
