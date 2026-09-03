@php
    $templatePlaceholders = [
        '{anrede}' => 'Anrede / Einstieg',
        '{name}' => 'Vollständiger Empfängername',
        '{vorname}' => 'Vorname',
        '{nachname}' => 'Nachname',
        '{email}' => 'E-Mail',
        '{telefon}' => 'Telefon',
        '{mitgliedsnummer}' => 'Mitgliedsnummer',
        '{firma}' => 'Organisation / Firma',
        '{strasse}' => 'Straße',
        '{plz}' => 'PLZ',
        '{ort}' => 'Ort',
        '{land}' => 'Land',
        '{verein}' => 'Verein',
        '{heute}' => 'Heutiges Datum',
        '{link}' => 'Individueller Link',
    ];
@endphp

<div>
    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
        @foreach ($templatePlaceholders as $placeholder => $label)
            <button type="button"
                    class="template-placeholder-button flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    data-placeholder="{{ $placeholder }}"
                    title="{{ $label }} einfügen">
                <span>{{ $label }}</span>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-slate-600">{{ $placeholder }}</span>
            </button>
        @endforeach
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.template-placeholder-button');
                if (!button) return;
                const placeholder = button.dataset.placeholder;
                const editor = window.tinymce ? tinymce.get('body') : null;
                if (editor && !editor.isHidden()) {
                    editor.focus();
                    editor.insertContent(placeholder);
                    return;
                }
                const textarea = document.getElementById('body');
                if (!textarea) return;
                const start = textarea.selectionStart ?? textarea.value.length;
                const end = textarea.selectionEnd ?? textarea.value.length;
                textarea.value = textarea.value.slice(0, start) + placeholder + textarea.value.slice(end);
                textarea.focus();
                textarea.setSelectionRange(start + placeholder.length, start + placeholder.length);
            });
        </script>
    @endpush
@endonce
