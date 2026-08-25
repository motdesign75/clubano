@extends('layouts.app')

@section('title', 'Betreiber-Mitteilung erstellen')

@php
    $selectedRecipientUserIds = collect(old('recipient_user_ids', []))->map(fn ($id) => (string) $id)->all();
@endphp

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('admin.announcements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">
            Zurück zu Mitteilungen
        </a>
    </div>

    <form id="operator-announcement-form" method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-6">
        @csrf

        <section class="rounded-3xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm sm:p-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-300">Betreiber-Mitteilung</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-normal sm:text-4xl">Update gestalten</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">
                        Verfasse Produktupdates im Clubano-Stil, teste sie an dich selbst und versende sie gezielt an Vereinsadmins.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm leading-6 text-slate-200">
                    Kein externer Editor, kein Tiny-Cloud-Key. Bilder werden direkt in Clubano gespeichert.
                </div>
            </div>
        </section>

        @if($errors->any())
            <section class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
                <div class="font-semibold text-rose-950">Bitte kurz prüfen.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-900">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <main class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="subject" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Betreff</label>
                            <input id="subject" name="subject" value="{{ old('subject', 'Neu in Clubano') }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('subject')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="cta_label" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Button-Text</label>
                            <input id="cta_label" name="cta_label" value="{{ old('cta_label', 'Clubano öffnen') }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="cta_url" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Button-Link</label>
                            <input id="cta_url" name="cta_url" value="{{ old('cta_url', config('app.url')) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('cta_url')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Editor</p>
                            <h2 class="mt-2 text-2xl font-semibold tracking-normal text-slate-950">Nachricht schreiben</h2>
                        </div>
                        <div class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            <span id="announcement-word-count">0</span>&nbsp;Wörter
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="body_markdown" class="sr-only">Nachricht</label>
                        <textarea id="body_markdown"
                                  name="body_markdown"
                                  rows="18"
                                  class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('body_markdown', $defaultBody) }}</textarea>
                        @error('body_markdown')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </section>
            </main>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Empfänger</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Versand steuern</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Du entscheidest bewusst, ob ein Filter greift oder einzelne Vereinsadmins angeschrieben werden.</p>

                    <div class="mt-5">
                        <label for="recipient_filter" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Auswahl</label>
                        <select id="recipient_filter" name="recipient_filter" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach($recipientFilters as $value => $label)
                                <option value="{{ $value }}" @selected(old('recipient_filter', 'all_active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <label for="test_email" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Testmail an</label>
                        <input id="test_email"
                               name="test_email"
                               type="email"
                               value="{{ old('test_email', auth()->user()->email) }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            Nur für „Testmail senden“. Der echte Versand nutzt die ausgewählten Vereinsadmins.
                        </p>
                        @error('test_email')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    <div id="selected-recipient-panel" class="mt-5 rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Konkrete Empfänger</div>
                                <p class="mt-1 text-sm leading-5 text-blue-950">Bei manueller Auswahl werden nur angehakte Personen angeschrieben.</p>
                            </div>
                            <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-blue-800">
                                <span id="selected-recipient-count">0</span> ausgewählt
                            </div>
                        </div>

                        <label for="recipient-search" class="sr-only">Empfänger suchen</label>
                        <input id="recipient-search" type="search" placeholder="Verein, Name oder E-Mail suchen" class="mt-4 w-full rounded-2xl border-blue-100 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                        <div id="recipient-list" class="mt-4 max-h-[34rem] space-y-3 overflow-y-auto pr-1">
                            @foreach($recipientOptions as $tenant)
                                <section class="recipient-tenant rounded-2xl border border-slate-200 bg-white p-3" data-search="{{ Str::lower($tenant->name.' '.$tenant->city.' '.$tenant->users->pluck('name')->join(' ').' '.$tenant->users->pluck('email')->join(' ')) }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-sm font-semibold text-slate-950">{{ $tenant->name }}</h3>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $tenant->city ?: 'Ort nicht hinterlegt' }} · {{ $tenant->users->count() }} Admins</p>
                                        </div>
                                    </div>

                                    <div class="mt-3 space-y-2">
                                        @foreach($tenant->users as $recipient)
                                            <label class="recipient-row flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 transition hover:border-blue-200 hover:bg-blue-50" data-search="{{ Str::lower($recipient->name.' '.$recipient->email) }}">
                                                <input type="checkbox"
                                                       name="recipient_user_ids[]"
                                                       value="{{ $recipient->id }}"
                                                       @checked(in_array((string) $recipient->id, $selectedRecipientUserIds, true))
                                                       class="recipient-checkbox mt-1 rounded border-slate-300 text-blue-700 focus:ring-blue-500">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $recipient->name ?: 'Ohne Namen' }}</span>
                                                    <span class="block truncate text-xs text-slate-500">{{ $recipient->email }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>

                        <p class="mt-3 text-xs leading-5 text-blue-900">
                            Ein Verein kann mehrere Admins haben. Deshalb wählst du hier bewusst die konkrete Person aus.
                        </p>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Vorschau</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">So kommt es an</h2>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div id="announcement-preview-subject" class="break-words text-base font-semibold text-slate-950">Neu in Clubano</div>
                        <div id="announcement-preview-body" class="prose prose-sm mt-4 max-h-96 max-w-none overflow-y-auto text-slate-700">{!! $previewHtml !!}</div>
                    </div>
                </section>

                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <h2 class="text-base font-semibold text-amber-950">Rechtlicher Rahmen</h2>
                    <p class="mt-2 text-sm leading-6 text-amber-800">
                        Diese Funktion ist für Produkt- und Betreiberhinweise an Vereinsadmins gedacht. Keine Werbung an Mitglieder, keine fremden Vereinsinhalte.
                    </p>
                </section>
            </aside>
        </div>

        <div class="sticky bottom-4 z-10 rounded-3xl border border-slate-200 bg-white/95 px-4 py-4 shadow-lg backdrop-blur sm:px-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('admin.announcements.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                    Abbrechen
                </a>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <button name="action" value="test" class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50">
                        Testmail senden
                    </button>
                    <button id="send-announcement-button" name="action" value="send" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">
                        An Vereinsadmins senden
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="/tinymce/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const subjectInput = document.getElementById('subject');
    const ctaLabelInput = document.getElementById('cta_label');
    const ctaUrlInput = document.getElementById('cta_url');
    const recipientFilter = document.getElementById('recipient_filter');
    const selectedRecipientPanel = document.getElementById('selected-recipient-panel');
    const selectedRecipientCount = document.getElementById('selected-recipient-count');
    const recipientSearch = document.getElementById('recipient-search');
    const sendButton = document.getElementById('send-announcement-button');
    const previewSubject = document.getElementById('announcement-preview-subject');
    const previewBody = document.getElementById('announcement-preview-body');
    const wordCount = document.getElementById('announcement-word-count');

    const countWords = (content) => {
        const plainText = String(content || '').replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').trim();
        return plainText === '' ? 0 : plainText.split(/\s+/).length;
    };

    const syncPreview = (editor = null) => {
        const body = editor ? editor.getContent() : document.getElementById('body_markdown')?.value || '';
        const subject = subjectInput?.value?.trim() || 'Ohne Betreff';
        const ctaLabel = ctaLabelInput?.value?.trim();
        const ctaUrl = ctaUrlInput?.value?.trim();

        previewSubject.textContent = subject;
        previewBody.innerHTML = body.trim() || '<span class="text-slate-400">Noch kein Inhalt.</span>';

        if (ctaLabel && ctaUrl) {
            previewBody.insertAdjacentHTML('beforeend', `<p><a href="${ctaUrl.replace(/"/g, '&quot;')}" class="inline-flex rounded-xl bg-blue-700 px-4 py-2 font-semibold text-white no-underline">${ctaLabel.replace(/</g, '&lt;')}</a></p>`);
        }

        wordCount.textContent = countWords(body);
    };

    const selectedCount = () => document.querySelectorAll('.recipient-checkbox:checked').length;

    const syncRecipients = () => {
        const manualMode = recipientFilter?.value === 'selected';

        selectedRecipientPanel?.classList.toggle('hidden', !manualMode);

        if (selectedRecipientCount) {
            selectedRecipientCount.textContent = selectedCount();
        }
    };

    const filterRecipients = () => {
        const query = String(recipientSearch?.value || '').trim().toLowerCase();

        document.querySelectorAll('.recipient-tenant').forEach((tenant) => {
            const tenantMatches = tenant.dataset.search?.includes(query);
            tenant.classList.toggle('hidden', query !== '' && !tenantMatches);
        });
    };

    tinymce.init({
        selector: '#body_markdown',
        license_key: 'gpl',
        height: 620,
        menubar: false,
        branding: false,
        statusbar: true,
        plugins: 'lists link image table code fullscreen autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | removeformat | code fullscreen',
        block_formats: 'Absatz=p; Überschrift=h2; Zwischenüberschrift=h3',
        image_title: true,
        image_caption: true,
        image_advtab: true,
        automatic_uploads: true,
        images_upload_url: '{{ route('admin.announcements.images.store') }}',
        images_upload_credentials: true,
        file_picker_types: 'image',
        file_picker_callback: (callback, value, meta) => {
            if (meta.filetype !== 'image') {
                return;
            }

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];

                if (!file) {
                    return;
                }

                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    const id = 'blobid' + (new Date()).getTime();
                    const blobCache = tinymce.activeEditor.editorUpload.blobCache;
                    const base64 = String(reader.result).split(',')[1];
                    const blobInfo = blobCache.create(id, file, base64);
                    blobCache.add(blobInfo);

                    callback(blobInfo.blobUri(), {
                        alt: file.name.replace(/\.[^.]+$/, ''),
                        title: file.name,
                    });
                });
                reader.readAsDataURL(file);
            });
            input.click();
        },
        images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());

            fetch('{{ route('admin.announcements.images.store') }}', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error('Bild konnte nicht hochgeladen werden.')))
                .then((json) => json.location ? resolve(json.location) : reject(new Error('Upload-Antwort war unvollständig.')))
                .catch((error) => reject(error.message));
        }),
        content_style: 'body{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;line-height:1.65;color:#0f172a;} h2,h3{line-height:1.25;margin:1.2em 0 .5em;} p{margin:.7em 0;} img{max-width:100%;height:auto;border-radius:14px;}',
        setup: (editor) => {
            editor.on('init keyup change input undo redo setcontent', () => syncPreview(editor));
        },
    });

    [subjectInput, ctaLabelInput, ctaUrlInput].forEach((input) => {
        input?.addEventListener('input', () => syncPreview(tinymce.get('body_markdown')));
    });

    recipientFilter?.addEventListener('change', syncRecipients);
    recipientSearch?.addEventListener('input', filterRecipients);
    document.querySelectorAll('.recipient-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', syncRecipients);
    });

    sendButton?.addEventListener('click', (event) => {
        const manualMode = recipientFilter?.value === 'selected';
        const count = selectedCount();

        if (manualMode && count === 0) {
            event.preventDefault();
            alert('Bitte wähle mindestens einen konkreten Empfänger aus.');
            return;
        }

        const message = manualMode
            ? `Diese Mitteilung wirklich an ${count} ausgewählte Empfänger senden?`
            : 'Diese Mitteilung wirklich an alle Empfänger dieses Filters senden?';

        if (!confirm(message)) {
            event.preventDefault();
        }
    });

    document.getElementById('operator-announcement-form')?.addEventListener('submit', () => {
        tinymce.triggerSave();
    });

    syncPreview();
    syncRecipients();
});
</script>
@endpush
