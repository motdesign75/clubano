@php
    $editing = isset($template) && $template;
    $nameValue = old('name', $template->name ?? '');
    $typeValue = old('type', $template->type ?? \App\Models\Template::TYPE_MAIL);
    $subjectValue = old('subject', $template->subject ?? '');
    $bodyValue = old('body', $template->body ?? '');
@endphp

<div class="space-y-6">
    @if ($errors->any())
        <section class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
            <div class="font-semibold text-rose-950">Bitte kurz prüfen.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-900">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <div class="grid gap-6 xl:grid-cols-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Vorlage</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Grundlage</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Name, Kanal und Betreff geben der Vorlage ihren Platz.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:col-span-3">
                <div>
                    <label for="name" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Name</label>
                    <input id="name" type="text" name="name" value="{{ $nameValue }}"
                           class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                           placeholder="z. B. Einladung Jahreshauptversammlung"
                           required>
                    @error('name')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="type" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Typ</label>
                    <select id="type" name="type"
                            class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                            required>
                        @foreach(($typeOptions ?? \App\Models\Template::typeOptions()) as $value => $label)
                            <option value="{{ $value }}" @selected($typeValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="subject" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Betreff / Überschrift</label>
                    <input id="subject" type="text" name="subject" value="{{ $subjectValue }}"
                           class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                           placeholder="z. B. Einladung zur Jahreshauptversammlung">
                    @error('subject')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </section>

    <section
        class="grid gap-6 xl:grid-cols-3"
    >
        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 xl:col-span-2">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Editor</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Text gestalten</h2>
                </div>
                <div class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    <span id="template-word-count">0</span>&nbsp;Wörter
                </div>
            </div>

            <div class="mt-5">
                <label for="body" class="sr-only">Text / Inhalt</label>
                <textarea name="body" id="body" rows="18" class="w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">{{ $bodyValue }}</textarea>
                @error('body')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Bausteine</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Platzhalter</h2>
                <div class="mt-4">
                    @include('templates.partials.placeholders')
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Aktion</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Button einfügen</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Für Anmeldungen, Antworten oder persönliche Links. Der Link darf auch ein Platzhalter sein.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="template-button-label" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Button-Text</label>
                        <input id="template-button-label" type="text" value="Jetzt öffnen" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                    </div>
                    <div>
                        <label for="template-button-url" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Link</label>
                        <input id="template-button-url" type="text" value="{link}" class="mt-2 w-full rounded-2xl border-slate-200 font-mono text-sm focus:border-slate-400 focus:ring-slate-300" placeholder="https://... oder {link}">
                    </div>
                    <button type="button"
                            id="template-insert-button"
                            class="inline-flex w-full items-center justify-center rounded-full bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Button in Vorlage einfügen
                    </button>
                    <p class="text-xs leading-5 text-slate-500">
                        Beispiel: <span class="font-mono">{link}</span> wird beim Versand durch einen individuellen Link ersetzt, wenn der Versand einen Link mitgibt.
                    </p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Vorschau</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">So wirkt die Vorlage</h2>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Betreff</div>
                    <div id="template-preview-subject" class="mt-2 break-words text-sm font-semibold text-slate-900">Ohne Betreff</div>
                </div>

                <div class="mt-4 max-h-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-800">
                    <div id="template-preview-body" class="text-slate-400">Noch kein Inhalt.</div>
                </div>
            </section>
        </aside>
    </section>

    <div class="sticky bottom-4 z-10 -mx-2 rounded-[28px] border border-slate-200 bg-white/95 px-4 py-4 shadow-lg backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:shadow-none sm:backdrop-blur-0">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('templates.index') }}"
               class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Zurück zur Übersicht
            </a>

            <button type="submit"
                    class="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                {{ $editing ? 'Änderungen speichern' : 'Vorlage speichern' }}
            </button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script src="/tinymce/tinymce.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const subjectInput = document.getElementById('subject');
                const bodyTextarea = document.getElementById('body');
                const previewSubject = document.getElementById('template-preview-subject');
                const previewBody = document.getElementById('template-preview-body');
                const wordCount = document.getElementById('template-word-count');

                const previewReplacements = {
                    '{anrede}': 'Guten Tag Max Mustermann',
                    '{name}': 'Max Mustermann',
                    '{vorname}': 'Max',
                    '{nachname}': 'Mustermann',
                    '{email}': 'max@example.org',
                    '{telefon}': '05181 123456',
                    '{mitgliedsnummer}': 'M-1001',
                    '{firma}': 'Musterorganisation',
                    '{strasse}': 'Musterweg 1',
                    '{plz}': '31157',
                    '{ort}': 'Sarstedt',
                    '{land}': 'Deutschland',
                    '{verein}': 'Musterverein',
                    '{heute}': new Date().toLocaleDateString('de-DE'),
                    '{link}': 'https://clubano.de/persoenlicher-link',
                };

                const replacePreviewPlaceholders = (content) => {
                    return Object.entries(previewReplacements).reduce((text, [placeholder, value]) => {
                        return text.split(placeholder).join(value);
                    }, String(content || ''));
                };

                const countWords = (content) => {
                    const plainText = String(content || '')
                        .replace(/<[^>]+>/g, ' ')
                        .replace(/&nbsp;/g, ' ')
                        .trim();

                    return plainText === '' ? 0 : plainText.split(/\s+/).length;
                };

                const syncPreview = (editor = null) => {
                    const rawBody = editor ? editor.getContent() : bodyTextarea?.value || '';
                    const subject = subjectInput?.value || '';
                    const renderedBody = replacePreviewPlaceholders(rawBody);

                    if (previewSubject) {
                        previewSubject.textContent = subject.trim() || 'Ohne Betreff';
                    }

                    if (previewBody) {
                        previewBody.classList.toggle('text-slate-400', renderedBody.trim() === '');
                        previewBody.innerHTML = renderedBody.trim() || 'Noch kein Inhalt.';
                    }

                    if (wordCount) {
                        wordCount.textContent = countWords(rawBody);
                    }
                };

                if (subjectInput) {
                    subjectInput.addEventListener('input', () => syncPreview(tinymce.get('body')));
                }

                if (bodyTextarea) {
                    bodyTextarea.addEventListener('input', () => syncPreview());
                }

                tinymce.init({
                    selector: '#body',
                    license_key: 'gpl',
                    height: 560,
                    menubar: false,
                    branding: false,
                    statusbar: true,
                    plugins: 'lists link image table code fullscreen autoresize',
                    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | removeformat | code fullscreen',
                    block_formats: 'Absatz=p; Überschrift 2=h2; Überschrift 3=h3',
                    image_title: true,
                    image_caption: true,
                    image_advtab: true,
                    paste_data_images: true,
                    automatic_uploads: true,
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
                                callback(reader.result, {
                                    alt: file.name.replace(/\.[^.]+$/, ''),
                                    title: file.name,
                                });
                            });
                            reader.readAsDataURL(file);
                        });

                        input.click();
                    },
                    content_style: 'body{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;font-size:15px;line-height:1.65;color:#0f172a;} h2,h3{line-height:1.25;margin:1.2em 0 .5em;} p{margin:.7em 0;}',
                    setup: (editor) => {
                        editor.on('init keyup change input undo redo setcontent', () => syncPreview(editor));
                    },
                });

                const insertTemplateButton = () => {
                    const label = (document.getElementById('template-button-label')?.value || 'Jetzt öffnen').trim();
                    const url = (document.getElementById('template-button-url')?.value || '{link}').trim();
                    const safeLabel = label
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                    const safeUrl = url
                        .replace(/&/g, '&amp;')
                        .replace(/"/g, '&quot;')
                        .replace(/</g, '')
                        .replace(/>/g, '');
                    const html = `<p style="margin:24px 0;"><a href="${safeUrl}" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:14px;padding:14px 22px;font-weight:700;">${safeLabel}</a></p>`;
                    const editor = window.tinymce ? tinymce.get('body') : null;

                    if (editor && !editor.isHidden()) {
                        editor.focus();
                        editor.insertContent(html);
                        syncPreview(editor);
                        return;
                    }

                    if (!bodyTextarea) {
                        return;
                    }

                    const start = bodyTextarea.selectionStart ?? bodyTextarea.value.length;
                    const end = bodyTextarea.selectionEnd ?? bodyTextarea.value.length;
                    bodyTextarea.value = bodyTextarea.value.slice(0, start) + html + bodyTextarea.value.slice(end);
                    bodyTextarea.focus();
                    bodyTextarea.setSelectionRange(start + html.length, start + html.length);
                    syncPreview();
                };

                document.getElementById('template-insert-button')?.addEventListener('click', insertTemplateButton);

                const form = document.getElementById('templateForm');
                if (form) {
                    form.addEventListener('submit', () => {
                        tinymce.triggerSave();
                    });
                }

                syncPreview();
            });
        </script>
    @endpush
@endonce
