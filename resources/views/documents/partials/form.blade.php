@php
    $tagValue = old('tags', $document?->tags ? implode(', ', $document->tags) : '');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte prüfe die markierten Felder.
        </div>
    @endif

    <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr),280px]">
        <div class="space-y-4">
            <div>
                <label for="title" class="text-sm font-semibold text-slate-900">Titel *</label>
                <input id="title" name="title" type="text" value="{{ old('title', $document?->title) }}" required
                       class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                       placeholder="z. B. Versicherungspolice 2026">
                @error('title') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="text-sm font-semibold text-slate-900">Kurzbeschreibung</label>
                <textarea id="description" name="description" rows="5"
                          class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                          placeholder="Worum geht es, was ist wichtig, wann wird es gebraucht?">{{ old('description', $document?->description) }}</textarea>
                @error('description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-sm font-semibold text-slate-900">Datei</div>
            @if($document)
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Aktuell: {{ $document->original_name }} · {{ $document->human_size }}
                </p>
            @endif
            <input id="file" name="file" type="file" @required(! $document)
                   class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
            <p class="mt-2 text-xs leading-5 text-slate-500">PDF, Bilder und Office-Dateien bis 50 MB.</p>
            @error('file') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </aside>
    </section>

    <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="category" class="text-sm font-semibold text-slate-900">Kategorie *</label>
            <select id="category" name="category" required class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                @foreach($categories as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', $document?->category ?? 'verein') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="text-sm font-semibold text-slate-900">Status *</label>
            <select id="status" name="status" required class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $document?->status ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="document_date" class="text-sm font-semibold text-slate-900">Dokumentdatum</label>
            <input id="document_date" name="document_date" type="date" value="{{ old('document_date', $document?->document_date?->format('Y-m-d')) }}"
                   class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
        </div>

        <div>
            <label for="expires_at" class="text-sm font-semibold text-slate-900">Ablaufdatum</label>
            <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at', $document?->expires_at?->format('Y-m-d')) }}"
                   class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="member_id" class="text-sm font-semibold text-slate-900">Mitglied</label>
            <select id="member_id" name="member_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                <option value="">Nicht verknüpfen</option>
                @foreach($members as $member)
                    <option value="{{ $member->id }}" @selected((string) old('member_id', $document?->member_id) === (string) $member->id)>{{ $member->full_name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="project_id" class="text-sm font-semibold text-slate-900">Projekt</label>
            <select id="project_id" name="project_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                <option value="">Nicht verknüpfen</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) old('project_id', $document?->project_id) === (string) $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="event_id" class="text-sm font-semibold text-slate-900">Termin</label>
            <select id="event_id" name="event_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                <option value="">Nicht verknüpfen</option>
                @foreach($events as $event)
                    <option value="{{ $event->id }}" @selected((string) old('event_id', $document?->event_id) === (string) $event->id)>{{ $event->title }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="protocol_id" class="text-sm font-semibold text-slate-900">Protokoll</label>
            <select id="protocol_id" name="protocol_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                <option value="">Nicht verknüpfen</option>
                @foreach($protocols as $protocol)
                    <option value="{{ $protocol->id }}" @selected((string) old('protocol_id', $document?->protocol_id) === (string) $protocol->id)>{{ $protocol->title }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="invoice_id" class="text-sm font-semibold text-slate-900">Rechnung</label>
            <select id="invoice_id" name="invoice_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                <option value="">Nicht verknüpfen</option>
                @foreach($invoices as $invoice)
                    <option value="{{ $invoice->id }}" @selected((string) old('invoice_id', $document?->invoice_id) === (string) $invoice->id)>{{ $invoice->invoice_number }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="tags" class="text-sm font-semibold text-slate-900">Tags</label>
            <input id="tags" name="tags" type="text" value="{{ $tagValue }}"
                   class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                   placeholder="Satzung, Vertrag, Vorstand">
        </div>
    </section>

    <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
        <a href="{{ $document ? route('documents.show', $document) : route('documents.index') }}"
           class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Abbrechen
        </a>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
            {{ $document ? 'Dokument speichern' : 'Dokument ablegen' }}
        </button>
    </div>
</form>
