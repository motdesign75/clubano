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
    ];
@endphp

<div class="rounded border border-indigo-100 bg-indigo-50 p-3">
    <div class="mb-2 text-sm font-semibold text-indigo-900">Platzhalter einfügen</div>

    <div class="flex flex-wrap gap-2">
        @foreach ($templatePlaceholders as $placeholder => $label)
            <button type="button" class="template-placeholder-button rounded border border-indigo-200 bg-white px-2.5 py-1 text-xs font-medium text-indigo-700 shadow-sm hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1" data-placeholder="{{ $placeholder }}" title="{{ $label }} einfügen">{{ $placeholder }}</button>
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
