@extends('layouts.app')

@section('title', 'Betreiber-Mitteilung erstellen')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8"
     x-data="operatorAnnouncementEditor(@js(old('body_markdown', $defaultBody)), @js($previewHtml))">
    <div class="mb-6">
        <a href="{{ route('admin.announcements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">
            Zurück zu Mitteilungen
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-600">Betreiber-Mitteilung</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-normal text-slate-950">Update verfassen</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Versand nur an Vereinsadmins. Nutze Testmail vor echtem Versand.
                </p>

                @if($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        Bitte prüfe die markierten Angaben.
                    </div>
                @endif

                <div class="mt-6 space-y-5">
                    <div>
                        <label for="subject" class="text-sm font-semibold text-slate-800">Betreff</label>
                        <input id="subject" name="subject" value="{{ old('subject', 'Neu in Clubano') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('subject')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label for="body_markdown" class="text-sm font-semibold text-slate-800">Nachricht</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700" @click="wrap('**', '**')">Fett</button>
                                <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700" @click="prefix('## ')">Überschrift</button>
                                <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700" @click="prefix('- ')">Liste</button>
                                <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700" @click="insertLink()">Link</button>
                            </div>
                        </div>
                        <textarea id="body_markdown"
                                  name="body_markdown"
                                  rows="16"
                                  x-model="body"
                                  @input="refreshPreview()"
                                  class="mt-1 w-full rounded-xl border-slate-300 font-mono text-sm leading-6 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('body_markdown', $defaultBody) }}</textarea>
                        <p class="mt-2 text-xs text-slate-500">
                            Formatierung: <strong>**fett**</strong>, <strong>## Überschrift</strong>, <strong>- Liste</strong>, <strong>[Text](https://...)</strong>
                        </p>
                        @error('body_markdown')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="cta_label" class="text-sm font-semibold text-slate-800">Button-Text</label>
                            <input id="cta_label" name="cta_label" value="{{ old('cta_label', 'Clubano öffnen') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="cta_url" class="text-sm font-semibold text-slate-800">Button-Link</label>
                            <input id="cta_url" name="cta_url" value="{{ old('cta_url', config('app.url')) }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('cta_url')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Empfänger</h2>
                <p class="mt-1 text-sm text-slate-500">Nur Vereinsadmins werden angeschrieben. Mitglieder bleiben außen vor.</p>

                <div class="mt-5">
                    <label for="recipient_filter" class="text-sm font-semibold text-slate-800">Auswahl</label>
                    <select id="recipient_filter" name="recipient_filter" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($recipientFilters as $value => $label)
                            <option value="{{ $value }}" @selected(old('recipient_filter', 'all_active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-5">
                    <label for="tenant_ids" class="text-sm font-semibold text-slate-800">Vereine für manuelle Auswahl</label>
                    <select id="tenant_ids" name="tenant_ids[]" multiple size="8" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($tenantOptions as $tenant)
                            <option value="{{ $tenant->id }}" @selected(in_array($tenant->id, old('tenant_ids', [])))>
                                {{ $tenant->name }}{{ $tenant->city ? ' · '.$tenant->city : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Nur relevant, wenn „Manuell ausgewählte Vereine“ gewählt ist.</p>
                </div>
            </section>

            <div class="flex flex-col gap-3 sm:flex-row">
                <button name="action" value="test" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50">
                    Testmail an mich
                </button>
                <button name="action" value="send" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800" onclick="return confirm('Diese Mitteilung wirklich an die ausgewählten Vereinsadmins senden?')">
                    An Vereinsadmins senden
                </button>
            </div>
        </form>

        <aside class="space-y-4">
            <section class="sticky top-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Live-Vorschau</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-950">So wirkt die Nachricht</h2>
                <div class="prose prose-sm mt-5 max-w-none rounded-xl border border-slate-100 bg-slate-50 p-4 text-slate-700" x-html="preview"></div>
            </section>

            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <h2 class="text-base font-semibold text-amber-950">Rechtlicher Rahmen</h2>
                <p class="mt-2 text-sm leading-6 text-amber-800">
                    Diese Funktion ist für Produkt- und Betreiberhinweise an Vereinsadmins gedacht. Keine Werbung an Mitglieder, keine fremden Vereinsinhalte.
                </p>
            </section>
        </aside>
    </div>
</div>

<script>
function operatorAnnouncementEditor(initialBody, initialPreview) {
    return {
        body: initialBody || '',
        preview: initialPreview || '',
        escape(value) {
            return String(value).replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[char]));
        },
        refreshPreview() {
            let html = this.escape(this.body)
                .replace(/^## (.*)$/gm, '<h2>$1</h2>')
                .replace(/^\- (.*)$/gm, '<li>$1</li>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, '<a href="$2">$1</a>')
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>');
            html = html.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
            this.preview = '<p>' + html + '</p>';
        },
        textarea() {
            return document.getElementById('body_markdown');
        },
        wrap(before, after) {
            const field = this.textarea();
            const start = field.selectionStart;
            const end = field.selectionEnd;
            const selected = this.body.slice(start, end) || 'Text';
            this.body = this.body.slice(0, start) + before + selected + after + this.body.slice(end);
            this.$nextTick(() => {
                field.focus();
                field.setSelectionRange(start + before.length, start + before.length + selected.length);
                this.refreshPreview();
            });
        },
        prefix(prefix) {
            const field = this.textarea();
            const start = field.selectionStart;
            const lineStart = this.body.lastIndexOf('\n', start - 1) + 1;
            this.body = this.body.slice(0, lineStart) + prefix + this.body.slice(lineStart);
            this.$nextTick(() => {
                field.focus();
                this.refreshPreview();
            });
        },
        insertLink() {
            const field = this.textarea();
            const start = field.selectionStart;
            const end = field.selectionEnd;
            const selected = this.body.slice(start, end) || 'Linktext';
            const link = `[${selected}](https://app.clubano.de)`;
            this.body = this.body.slice(0, start) + link + this.body.slice(end);
            this.$nextTick(() => {
                field.focus();
                this.refreshPreview();
            });
        },
        init() {
            this.refreshPreview();
        }
    };
}
</script>
@endsection
