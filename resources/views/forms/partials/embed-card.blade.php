@php
    $publicUrl = route('forms.public.show', $form->slug);
    $embedUrl = route('forms.public.embed', $form->slug);
    $iframeCode = '<iframe src="' . $embedUrl . '" width="100%" height="920" style="border:0;max-width:100%;" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
@endphp

<div id="einbettung" class="rounded-xl bg-white p-6 shadow">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Einbettung</h2>
        <p class="text-sm text-gray-500">Nutze das Formular direkt per Link oder binde es per iFrame in deine Webseite ein.</p>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Oeffentlicher Link</label>
            <input type="text" readonly value="{{ $publicUrl }}" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 text-sm">
        </div>

        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <label class="block text-sm font-medium text-gray-700">Embed-URL</label>
                <input type="text" readonly value="{{ $embedUrl }}" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 text-sm">
            </div>

            <a href="{{ $embedUrl }}"
               target="_blank"
               class="inline-flex shrink-0 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Vorschau
            </a>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">iFrame-Code</label>
            <textarea readonly rows="4" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 font-mono text-xs">{{ $iframeCode }}</textarea>
        </div>

        <p class="text-xs leading-5 text-gray-500">
            Falls die Einbettung in einer externen Webseite blockiert wird, sitzt meist noch ein Server-Header davor.
            Dann muessen wir auf dem Zielserver `X-Frame-Options` bzw. `Content-Security-Policy` passend freigeben.
        </p>
    </div>
</div>
