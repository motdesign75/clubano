@php
    $formPlaceholders = [
        '{anrede}' => 'Begrüßung anhand der Angaben',
        '{name}' => 'Vollständiger Name',
        '{vorname}' => 'Vorname',
        '{nachname}' => 'Nachname',
        '{email}' => 'E-Mail aus dem Formular',
        '{telefon}' => 'Telefon / Mobil',
        '{verein}' => 'Vereinsname',
        '{formular}' => 'Formulartitel',
        '{heute}' => 'Heutiges Datum',
        '{message}' => 'Beispiel: Feld mit dem Slug message',
    ];
    $targetId = $targetId ?? 'confirmation_mail_body';
@endphp

<div class="rounded border border-emerald-100 bg-emerald-50 p-3">
    <div class="mb-2 text-sm font-semibold text-emerald-900">Platzhalter für Bestätigungsmail</div>
    <p class="mb-3 text-xs text-emerald-800">
        Alle Formular-Slugs funktionieren auch direkt als Platzhalter, z. B. <span class="font-semibold">{'{city}'}</span> oder <span class="font-semibold">{'{favorite_beer}'}</span>.
    </p>

    <div class="flex flex-wrap gap-2">
        @foreach ($formPlaceholders as $placeholder => $label)
            <button type="button"
                    class="form-placeholder-button rounded border border-emerald-200 bg-white px-2.5 py-1 text-xs font-medium text-emerald-700 shadow-sm hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
                    data-placeholder="{{ $placeholder }}"
                    data-target="{{ $targetId }}"
                    title="{{ $label }} einfügen">
                {{ $placeholder }}
            </button>
        @endforeach
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.form-placeholder-button');
                if (!button) return;

                const placeholder = button.dataset.placeholder;
                const targetId = button.dataset.target;
                const editor = window.tinymce ? tinymce.get(targetId) : null;

                if (editor && !editor.isHidden()) {
                    editor.focus();
                    editor.insertContent(placeholder);
                    return;
                }

                const textarea = document.getElementById(targetId);
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
