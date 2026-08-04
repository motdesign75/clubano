@php
    $currentHelpKey = trim($__env->yieldContent('help-key'));
    $pageHelp = $currentHelpKey !== '' ? config('clubano_help.pages.' . $currentHelpKey) : null;
    $hasPageHelp = is_array($pageHelp) && !empty($pageHelp);
    $currentTenant = auth()->user()?->tenant;
    $isDemoTenant = (bool) ($currentTenant?->is_demo ?? false);
    $updateNotice = config('clubano.update_notice', []);
    $updateNoticeVersion = (string) ($updateNotice['version'] ?? '');
    $showUpdateNotice = auth()->check()
        && $updateNoticeVersion !== ''
        && auth()->user()?->update_notice_dismissed_version !== $updateNoticeVersion;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{
          open: false,
          helpOpen: false
      }"
      x-cloak
      class="h-full bg-gray-100">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Clubano'))</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/clubano-icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('images/clubano-icon.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <script src="{{ asset('vendor/livewire/livewire.js') }}"
            data-turbo-eval="false"
            data-csrf="{{ csrf_token() }}"></script>

    @stack('head')
</head>

<body class="h-full antialiased font-sans text-gray-800">

<!-- Mobiler Header -->
<header class="sm:hidden fixed top-0 left-0 right-0 z-40 bg-white shadow-md px-4 py-3 flex justify-between items-center">
    <div class="text-xl font-semibold">Clubano</div>

    <div class="flex items-center gap-2">
        @if($hasPageHelp)
            <button type="button"
                    @click="helpOpen = true"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    aria-label="Seitenhilfe öffnen">
                ?
            </button>
        @endif

        @auth
            <button type="button"
                    data-feedback-open
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-lg text-slate-700 shadow-sm transition hover:bg-slate-50"
                    aria-label="Feedback senden">
                💬
            </button>
        @endauth

        <button @click="open = !open"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-gray-700 shadow-sm transition hover:bg-slate-50"
                aria-label="Menü öffnen">
            <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>
</header>

<!-- Mobile Sidebar -->
<div x-show="open"
     x-transition
     class="sm:hidden fixed top-0 left-0 z-50 w-64 h-full bg-white shadow-lg overflow-y-auto"
     x-cloak>

    <div class="relative h-full">
        <button @click="open = false" class="absolute top-3 right-3 text-gray-600">✕</button>

        <div class="p-4 mt-10" x-data="{ collapsed: false }">
            @include('layouts.sidebar')
        </div>
    </div>
</div>

<!-- Overlay -->
<div x-show="open"
     class="sm:hidden fixed inset-0 bg-black bg-opacity-25 z-40"
     x-cloak
     @click="open = false">
</div>

@if($hasPageHelp)
    <div x-show="helpOpen"
         x-transition.opacity
         class="fixed inset-0 z-[70] bg-slate-950/30"
         x-cloak
         @click="helpOpen = false">
    </div>

    <aside x-show="helpOpen"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-full opacity-0"
           x-transition:enter-end="translate-x-0 opacity-100"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="translate-x-0 opacity-100"
           x-transition:leave-end="translate-x-full opacity-0"
           class="fixed right-0 top-0 z-[80] flex h-full w-full max-w-xl flex-col border-l border-slate-200 bg-white shadow-2xl"
           x-cloak
           @keydown.escape.window="helpOpen = false">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-5 sm:px-6">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Hilfe</div>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ $pageHelp['title'] ?? 'Diese Seite verstehen' }}</h2>
                @if(!empty($pageHelp['summary']))
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $pageHelp['summary'] }}</p>
                @endif
            </div>

            <button type="button"
                    @click="helpOpen = false"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-lg text-slate-500 transition hover:bg-slate-50">
                ×
            </button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5 sm:px-6">
            @if(!empty($pageHelp['steps']))
                <section class="rounded-3xl border border-slate-200 bg-slate-50/70 p-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Ablauf</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">So gehst du hier am besten vor</h3>

                    <div class="mt-4 space-y-4">
                        @foreach($pageHelp['steps'] as $step)
                            <div class="flex gap-3">
                                <div class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">
                                    {{ $loop->iteration }}
                                </div>
                                <div class="text-sm leading-6 text-slate-700">{{ $step }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(!empty($pageHelp['tips']))
                <section class="rounded-3xl border border-amber-200 bg-amber-50/70 p-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Gut zu wissen</div>
                    <div class="mt-4 space-y-3">
                        @foreach($pageHelp['tips'] as $tip)
                            <div class="text-sm leading-6 text-amber-950">{{ $tip }}</div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if(!empty($pageHelp['faq']))
                <section class="space-y-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Fragen</div>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">Typische Unsicherheiten</h3>
                    </div>

                    @foreach($pageHelp['faq'] as $entry)
                        <details class="rounded-2xl border border-slate-200 bg-white p-4">
                            <summary class="cursor-pointer list-none text-sm font-semibold text-slate-900">
                                {{ $entry['question'] ?? '' }}
                            </summary>
                            <div class="mt-3 text-sm leading-6 text-slate-600">
                                {{ $entry['answer'] ?? '' }}
                            </div>
                        </details>
                    @endforeach
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Noch nicht klar?</div>
                <h3 class="mt-2 text-lg font-semibold text-slate-900">Direkt Rueckmeldung geben</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Wenn dir auf dieser Seite etwas fehlt oder unklar bleibt, sende direkt Feedback aus der Anwendung. So kann die Hilfe genau dort besser werden, wo sie gebraucht wird.
                </p>
                @auth
                    <button type="button"
                            data-feedback-open
                            @click="helpOpen = false"
                            class="mt-4 inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Feedback zu dieser Seite
                    </button>
                @endauth
            </section>
        </div>
    </aside>
@endif

<!-- ❗ WICHTIG: overflow-hidden entfernt -->
<div class="flex h-screen">

    <!-- Sidebar Desktop -->
    <aside
        x-data="{
            collapsed: false,
            init() {
                this.collapsed = localStorage.getItem('clubano.sidebar.collapsed') === '1';
            },
            toggle() {
                this.collapsed = !this.collapsed;
                localStorage.setItem('clubano.sidebar.collapsed', this.collapsed ? '1' : '0');
            }
        }"
        :class="collapsed ? 'sm:w-24' : 'sm:w-72'"
        class="hidden sm:flex sm:flex-col bg-white border-r border-indigo-100 shadow z-30 overflow-y-auto transition-all duration-200">
        <div class="h-full">
            @include('layouts.sidebar')
        </div>
    </aside>

    <!-- Content -->
    <main class="flex-1 w-full overflow-y-auto px-4 pt-[56px] sm:pt-6 transition-all duration-200">
        @if(session('warning'))
            <div class="mx-auto max-w-7xl px-4 pb-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">
                    {{ session('warning') }}
                </div>
            </div>
        @endif

        @if($isDemoTenant)
            <div class="mx-auto max-w-7xl px-4 pb-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 text-sm text-blue-950">
                    <div class="font-semibold">Demo-Modus</div>
                    <div class="mt-1 leading-6">
                        Du testest Clubano mit Beispieldaten. Anlegen und Bearbeiten ist möglich; Mailversand, SEPA, Rechnungsversand, Benutzerverwaltung, Vereinsdaten und Löschaktionen sind geschützt.
                    </div>
                </div>
            </div>
        @endif

        @if($showUpdateNotice)
            <div class="mx-auto max-w-7xl px-4 pb-4 sm:px-6 lg:px-8">
                <section class="rounded-2xl border border-blue-200 bg-white px-4 py-4 shadow-sm sm:px-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">Update {{ $updateNoticeVersion }}</div>
                            <h2 class="mt-2 text-lg font-semibold text-slate-950">{{ $updateNotice['title'] ?? 'Clubano wurde aktualisiert' }}</h2>
                            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">{{ $updateNotice['summary'] ?? '' }}</p>

                            @if(!empty($updateNotice['items']))
                                <div class="mt-4 grid gap-2 md:grid-cols-2">
                                    @foreach($updateNotice['items'] as $item)
                                        <div class="flex gap-2 text-sm leading-6 text-slate-700">
                                            <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                                            <span>{{ $item }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('update-notice.dismiss') }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 transition hover:bg-white">
                                Nicht mehr anzeigen
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        @endif

        @if($hasPageHelp)
            <div class="mx-auto hidden max-w-7xl justify-end px-4 pb-2 sm:flex sm:px-6 lg:px-8">
                <button type="button"
                        @click="helpOpen = true"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-900 text-xs text-white">?</span>
                    <span>Hilfe zu dieser Seite</span>
                </button>
            </div>
        @endif
        @yield('content')
    </main>

</div>

{{-- ================= FEEDBACK ================= --}}
@auth

<!-- Desktop-Trigger: präsent, aber nicht im Weg -->
<button id="feedbackToggle"
    type="button"
    style="position: fixed; bottom: 20px; right: 20px; z-index: 99999;"
    class="hidden lg:flex items-center justify-center rounded-full bg-[#2954A3] px-4 py-3 text-white shadow-lg transition hover:scale-[1.02] hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-[#2954A3] focus:ring-offset-2">
    <div class="flex items-center gap-2">
        <span class="text-lg leading-none">💬</span>
        🗣️ Feedback
    </div>
</button>

<!-- Modal -->
<div id="feedbackModal"
     class="fixed inset-0 hidden z-[99999] bg-slate-950/45 p-0 sm:p-4">

    <div class="flex min-h-full items-end justify-center sm:items-center">
        <div class="relative w-full rounded-t-3xl bg-white p-5 shadow-xl sm:max-w-lg sm:rounded-3xl sm:p-6">

            <button type="button"
                    onclick="closeFeedback()"
                    class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-lg text-slate-500 transition hover:bg-slate-50">
                ×
            </button>

            <div class="pr-12">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Clubano verbessern</div>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">
                    Feedback
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Kurz, direkt und ohne Umwege. Was stoert, fehlt oder richtig gut funktioniert.
                </p>
            </div>

            <form method="POST" action="{{ route('feedback.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="feedback-category" class="mb-1 block text-sm font-medium text-slate-700">Kategorie</label>
                    <select id="feedback-category"
                            name="category"
                            class="w-full rounded-2xl border-slate-300">
                        <option value="Fehler">Fehler</option>
                        <option value="Verbesserung">Verbesserung</option>
                        <option value="Allgemein">Allgemein</option>
                    </select>
                </div>

                <input type="hidden" name="view" value="{{ Route::currentRouteName() }}">
                <input type="hidden" name="url" value="{{ url()->full() }}">
                <input type="hidden" name="page_title" id="feedback-page-title" value="">
                <input type="hidden" name="device_label" id="feedback-device-label" value="">
                <input type="hidden" name="viewport" id="feedback-viewport" value="">
                <input type="hidden" name="user_agent" id="feedback-user-agent" value="">
                <input type="hidden" name="screenshot_data" id="feedback-screenshot-data" value="">
                <input type="file" name="screenshot_file" id="feedback-screenshot-file" accept="image/*" class="hidden">

                <div>
                    <label for="feedback-message" class="mb-1 block text-sm font-medium text-slate-700">Nachricht</label>
                    <textarea id="feedback-message"
                              name="message"
                              required
                              rows="6"
                              class="w-full rounded-2xl border-slate-300"
                              placeholder="Zum Beispiel: Auf dem iPad verdeckt Element X den Inhalt ..."></textarea>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">Kontext automatisch mitsenden</div>
                            <div class="mt-1 text-sm text-slate-500">
                                Seite, Geraetetyp und Bildschirmgroesse werden automatisch erfasst. Optional kannst du einen Screenshot aufnehmen oder ein Bild hochladen.
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button"
                                    id="feedback-upload"
                                    class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                                Bild hochladen
                            </button>
                            <button type="button"
                                    id="feedback-capture"
                                    class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                                Screenshot aufnehmen
                            </button>
                        </div>
                    </div>

                    <div id="feedback-screenshot-preview" class="mt-4 hidden">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <img id="feedback-screenshot-image" src="" alt="Screenshot Vorschau" class="h-auto w-full">
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                Screenshot wird mitgesendet
                            </span>
                            <button type="button"
                                    id="feedback-screenshot-remove"
                                    class="inline-flex items-center rounded-full border border-rose-200 bg-white px-3 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">
                                Entfernen
                            </button>
                        </div>
                        <div id="feedback-screenshot-meta" class="mt-2 text-xs text-slate-500"></div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button"
                            onclick="closeFeedback()"
                            class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-full bg-[#2954A3] px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                        Feedback senden
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const toggleBtn = document.getElementById('feedbackToggle');
    const inlineTriggers = document.querySelectorAll('[data-feedback-open]');
    const modal = document.getElementById('feedbackModal');
    const messageField = document.getElementById('feedback-message');
    const captureBtn = document.getElementById('feedback-capture');
    const uploadBtn = document.getElementById('feedback-upload');
    const screenshotDataField = document.getElementById('feedback-screenshot-data');
    const screenshotFileField = document.getElementById('feedback-screenshot-file');
    const screenshotPreview = document.getElementById('feedback-screenshot-preview');
    const screenshotImage = document.getElementById('feedback-screenshot-image');
    const screenshotRemove = document.getElementById('feedback-screenshot-remove');
    const screenshotMeta = document.getElementById('feedback-screenshot-meta');
    const pageTitleField = document.getElementById('feedback-page-title');
    const deviceLabelField = document.getElementById('feedback-device-label');
    const viewportField = document.getElementById('feedback-viewport');
    const userAgentField = document.getElementById('feedback-user-agent');
    const maxScreenshotBytes = 900 * 1024;

    const updateFeedbackContext = () => {
        if (pageTitleField) pageTitleField.value = document.title;
        if (viewportField) viewportField.value = `${window.innerWidth}x${window.innerHeight}`;
        if (userAgentField) userAgentField.value = navigator.userAgent || '';

        let label = 'Desktop';
        if (window.innerWidth < 640) {
            label = 'Smartphone';
        } else if (window.innerWidth < 1024) {
            label = 'Tablet';
        }

        if (deviceLabelField) {
            deviceLabelField.value = label;
        }
    };

    const clearScreenshot = () => {
        if (screenshotDataField) screenshotDataField.value = '';
        if (screenshotFileField) screenshotFileField.value = '';
        if (screenshotImage) screenshotImage.src = '';
        if (screenshotMeta) screenshotMeta.textContent = '';
        screenshotPreview?.classList.add('hidden');
    };

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0 KB';
        }

        if (bytes >= 1024 * 1024) {
            return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
        }

        return `${Math.round(bytes / 1024)} KB`;
    };

    const dataUrlSize = (dataUrl) => {
        const base64 = (dataUrl || '').split(',')[1] || '';
        return Math.ceil((base64.length * 3) / 4);
    };

    const canvasToDataUrl = (canvas, quality) => canvas.toDataURL('image/jpeg', quality);

    const buildOptimizedScreenshot = (video) => {
        const maxWidth = 1600;
        const maxHeight = 1600;
        const scale = Math.min(1, maxWidth / video.videoWidth, maxHeight / video.videoHeight);
        const width = Math.max(1, Math.round(video.videoWidth * scale));
        const height = Math.max(1, Math.round(video.videoHeight * scale));

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d', { alpha: false });
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(video, 0, 0, width, height);

        let quality = 0.82;
        let dataUrl = canvasToDataUrl(canvas, quality);

        while (dataUrlSize(dataUrl) > maxScreenshotBytes && quality > 0.45) {
            quality -= 0.08;
            dataUrl = canvasToDataUrl(canvas, quality);
        }

        if (dataUrlSize(dataUrl) > maxScreenshotBytes) {
            const smallerCanvas = document.createElement('canvas');
            smallerCanvas.width = Math.max(1, Math.round(width * 0.75));
            smallerCanvas.height = Math.max(1, Math.round(height * 0.75));

            const smallerContext = smallerCanvas.getContext('2d', { alpha: false });
            smallerContext.fillStyle = '#ffffff';
            smallerContext.fillRect(0, 0, smallerCanvas.width, smallerCanvas.height);
            smallerContext.drawImage(canvas, 0, 0, smallerCanvas.width, smallerCanvas.height);

            quality = 0.72;
            dataUrl = canvasToDataUrl(smallerCanvas, quality);

            while (dataUrlSize(dataUrl) > maxScreenshotBytes && quality > 0.42) {
                quality -= 0.08;
                dataUrl = canvasToDataUrl(smallerCanvas, quality);
            }
        }

        return dataUrl;
    };

    const showPreview = (source, metaText) => {
        if (screenshotImage) screenshotImage.src = source;
        if (screenshotMeta) screenshotMeta.textContent = metaText || '';
        screenshotPreview?.classList.remove('hidden');
    };

    const openFeedback = () => {
        updateFeedbackContext();
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        window.setTimeout(() => messageField?.focus(), 60);
    };

    if (toggleBtn && modal) {
        toggleBtn.addEventListener('click', openFeedback);
    }

    window.closeFeedback = function () {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    inlineTriggers.forEach((trigger) => {
        trigger.addEventListener('click', openFeedback);
    });

    modal?.addEventListener('click', function (event) {
        if (event.target === modal) {
            window.closeFeedback();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            window.closeFeedback();
        }
    });

    captureBtn?.addEventListener('click', async function () {
        if (!navigator.mediaDevices?.getDisplayMedia) {
            alert('Screenshot-Aufnahme wird in diesem Browser leider nicht unterstuetzt. Bitte nutze stattdessen "Bild hochladen".');
            return;
        }

        try {
            updateFeedbackContext();

            const stream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    preferCurrentTab: true,
                },
                audio: false,
            });

            const video = document.createElement('video');
            video.srcObject = stream;
            video.playsInline = true;

            await new Promise((resolve) => {
                video.onloadedmetadata = resolve;
            });
            await video.play();
            await new Promise((resolve) => window.setTimeout(resolve, 150));

            stream.getTracks().forEach((track) => track.stop());

            const dataUrl = buildOptimizedScreenshot(video);
            if (!dataUrl || dataUrlSize(dataUrl) < 2048) {
                clearScreenshot();
                alert('Der Screenshot konnte nicht sauber erfasst werden. Bitte nutze alternativ "Bild hochladen".');
                return;
            }

            if (screenshotFileField) screenshotFileField.value = '';
            if (screenshotDataField) screenshotDataField.value = dataUrl;
            showPreview(dataUrl, `Komprimiert fuer schnellen Versand · ${formatBytes(dataUrlSize(dataUrl))}`);
        } catch (error) {
            console.warn('Feedback screenshot capture aborted', error);
        }
    });

    uploadBtn?.addEventListener('click', function () {
        screenshotFileField?.click();
    });

    screenshotFileField?.addEventListener('change', function (event) {
        const file = event.target.files?.[0];

        if (!file) {
            clearScreenshot();
            return;
        }

        if (screenshotDataField) screenshotDataField.value = '';

        const reader = new FileReader();
        reader.onload = function (loadEvent) {
            showPreview(String(loadEvent.target?.result || ''), `Datei ausgewaehlt · ${formatBytes(file.size)}`);
        };
        reader.readAsDataURL(file);
    });

    screenshotRemove?.addEventListener('click', clearScreenshot);

    updateFeedbackContext();

});
</script>

@endauth

@livewireScripts
@stack('scripts')

</body>
</html>
