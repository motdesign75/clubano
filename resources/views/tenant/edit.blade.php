@extends('layouts.app')

@section('title', 'Vereinsdaten bearbeiten')

@section('content')
<div class="max-w-5xl mx-auto space-y-10 text-gray-800 py-10">

    <h1 class="text-3xl font-extrabold text-[#2954A3]">
        ✏️ Vereinsdaten bearbeiten
    </h1>

    <form method="POST"
          action="{{ route('tenant.update') }}"
          enctype="multipart/form-data"
          class="bg-white shadow-xl ring-1 ring-gray-200 rounded-2xl p-8 space-y-10"
          aria-labelledby="form-vereinsdaten">
        @csrf
        @method('PATCH')

        {{-- Logo --}}
        <section>
            <h2 id="form-vereinsdaten" class="text-xl font-semibold text-gray-700 mb-4">📛 Vereinslogo</h2>
            @if($tenant->logo_url)
                <div class="mb-4">
                    <img src="{{ route('tenant.logo') }}" alt="Vereinslogo" class="h-24 rounded shadow inline-block">
                </div>
            @endif
            <input type="file" name="logo" accept="image/*"
                   class="file:rounded file:border file:bg-indigo-50 file:text-indigo-700 file:px-4 file:py-2 w-full border border-gray-300 rounded-lg shadow-sm">
        </section>

        {{-- Stammdaten --}}
        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">🏢 Stammdaten</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input name="name" label="Name *" :value="old('name', $tenant->name)" required />
                <x-ui.input name="slug" label="Slug" :value="old('slug', $tenant->slug)" help="Eindeutiger Bezeichner (z. B. für URLs)" />
                <x-ui.input name="email" label="E-Mail *" type="email" :value="old('email', $tenant->email)" required />
                <x-ui.input name="phone" label="Telefon" :value="old('phone', $tenant->phone)" />
                <x-ui.input name="register_number" label="Registernummer" :value="old('register_number', $tenant->register_number)" />
                <x-ui.input name="creditor_identifier" label="Gläubiger-ID" :value="old('creditor_identifier', $tenant->creditor_identifier)" help="Erforderlich für SEPA-Lastschriftläufe" />
                <x-ui.input name="chairman_name" label="Vorsitzender / Vorsitzende" :value="old('chairman_name', $tenant->chairman_name)" />
            </div>
        </section>

        {{-- Vorstandsunterschriften --}}
        @php
            $signatoryRows = old('board_signatories', $tenant->board_signatories ?? []);
            $defaultSignatoryRows = [
                ['name' => $tenant->chairman_name ?: '', 'role' => '1. Vorsitz', 'enabled' => filled($tenant->chairman_name)],
                ['name' => '', 'role' => '2. Vorsitz', 'enabled' => false],
                ['name' => '', 'role' => 'Kasse', 'enabled' => false],
                ['name' => '', 'role' => 'Schriftführung', 'enabled' => false],
            ];
            $signatoryRows = collect($signatoryRows ?: $defaultSignatoryRows)
                ->pad(4, ['name' => '', 'role' => '', 'enabled' => false])
                ->take(6)
                ->values();
        @endphp

        <section>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">✍️ Geschäftsführender Vorstand & Unterschriften</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Markiere hier, welche Namen in Briefen und E-Mails als Unterschriftenzeile erscheinen sollen. Bei einem Vorstandswechsel änderst du nur diese Liste.
                        </p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                        Platzhalter: {vorstand_unterschriften}
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach($signatoryRows as $index => $signatory)
                        <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr),minmax(0,220px),auto] md:items-end">
                            <div>
                                <label for="board_signatories_{{ $index }}_name" class="block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Name</label>
                                <input id="board_signatories_{{ $index }}_name"
                                       type="text"
                                       name="board_signatories[{{ $index }}][name]"
                                       value="{{ $signatory['name'] ?? '' }}"
                                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                       placeholder="z. B. Olli Towet">
                            </div>
                            <div>
                                <label for="board_signatories_{{ $index }}_role" class="block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Funktion</label>
                                <input id="board_signatories_{{ $index }}_role"
                                       type="text"
                                       name="board_signatories[{{ $index }}][role]"
                                       value="{{ $signatory['role'] ?? '' }}"
                                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                       placeholder="z. B. 1. Vorsitz">
                            </div>
                            <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
                                <input type="hidden" name="board_signatories[{{ $index }}][enabled]" value="0">
                                <input type="checkbox"
                                       name="board_signatories[{{ $index }}][enabled]"
                                       value="1"
                                       @checked((bool) ($signatory['enabled'] ?? false))
                                       class="rounded border-slate-300 text-slate-950 focus:ring-slate-500">
                                Verwenden
                            </label>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-xs leading-5 text-slate-500">
                    Hinweis: Die Schreibschrift ist eine optische Gruß- und Abschlusszeile, keine rechtssichere digitale Signatur.
                </p>
            </div>
        </section>

        {{-- Adresse --}}
        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">📍 Adresse</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input name="address" label="Straße / Adresse" :value="old('address', $tenant->address)" />
                <x-ui.input name="zip" label="PLZ" :value="old('zip', $tenant->zip)" />
                <x-ui.input name="city" label="Ort" :value="old('city', $tenant->city)" />
            </div>
        </section>

        {{-- Bankdaten --}}
        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">🏦 Bankverbindung</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input name="iban" label="IBAN" :value="old('iban', $tenant->iban)" />
                <x-ui.input name="bic" label="BIC" :value="old('bic', $tenant->bic)" />
                <x-ui.input name="bank_name" label="Bankname" :value="old('bank_name', $tenant->bank_name)" />
            </div>
        </section>

        {{-- Briefbogen Upload --}}
        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">📄 Briefbogen (PDF oder Bild)</h2>
            @if($tenant->pdf_template)
                @php
                    $ext = strtolower(pathinfo($tenant->pdf_template, PATHINFO_EXTENSION));
                @endphp
                <div class="mb-4">
                    <label class="text-sm text-gray-600 block mb-1">Aktuell:</label>
                    @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                        <img src="{{ route('tenant.letterhead') }}" alt="Briefbogen" class="max-h-40 object-contain border rounded shadow">
                    @else
                        <a href="{{ route('tenant.letterhead') }}" target="_blank" class="text-[#2954A3] hover:underline">
                            {{ basename($tenant->pdf_template) }} anzeigen
                        </a>
                    @endif
                </div>
            @endif
            <input type="file" name="pdf_template" accept="application/pdf,image/jpeg,image/png"
                   class="file:rounded file:border file:bg-pink-50 file:text-pink-700 file:px-4 file:py-2 w-full border border-gray-300 rounded-lg shadow-sm mt-2">
            <div class="mt-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="use_letterhead" value="1"
                           {{ old('use_letterhead', $tenant->use_letterhead) ? 'checked' : '' }}
                           class="rounded border-gray-300">
                    <span class="ml-2 text-sm text-gray-700">Briefbogen in PDFs als Hintergrund verwenden</span>
                </label>
            </div>
            <div class="mt-5 max-w-xs">
                <label for="letter_bottom_margin_mm" class="block text-sm font-medium text-gray-700">
                    Abstand zur Fußzeile
                </label>
                <div class="mt-1 flex rounded-xl shadow-sm">
                    <input
                        type="number"
                        min="15"
                        max="80"
                        step="1"
                        name="letter_bottom_margin_mm"
                        id="letter_bottom_margin_mm"
                        value="{{ old('letter_bottom_margin_mm', $tenant->letter_bottom_margin_mm ?? 30) }}"
                        class="block w-full rounded-l-xl border-gray-300 focus:border-[#2954A3] focus:ring-[#2954A3]"
                    >
                    <span class="inline-flex items-center rounded-r-xl border border-l-0 border-gray-300 bg-gray-50 px-4 text-sm text-gray-600">mm</span>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Mehr Abstand hilft, wenn dein Briefbogen unten eine hohe Fußzeile hat.
                </p>
                @error('letter_bottom_margin_mm')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <section x-data="{ exitMailEnabled: {{ old('member_exit_mail_enabled', $tenant->member_exit_mail_enabled) ? 'true' : 'false' }} }">
            <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-slate-50 p-6">
                <div class="max-w-3xl">
                    <h2 class="text-xl font-semibold text-gray-800">💌 Austrittsbestaetigung per E-Mail</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Wenn ein Austrittsdatum neu gesetzt oder geaendert wird, kann Clubano dem Mitglied automatisch eine wuerdige Bestaetigung senden. Jeder Verein formuliert diese Mail selbst.
                    </p>
                </div>

                <label class="inline-flex items-center gap-3">
                    <input type="checkbox"
                           name="member_exit_mail_enabled"
                           value="1"
                           x-model="exitMailEnabled"
                           @checked(old('member_exit_mail_enabled', $tenant->member_exit_mail_enabled))
                           class="rounded border-gray-300">
                    <span class="text-sm font-medium text-slate-800">Austrittsmail automatisch senden</span>
                </label>

                <div x-show="exitMailEnabled" x-cloak class="space-y-4">
                    <div class="grid grid-cols-1 gap-6">
                        <x-ui.input name="member_exit_mail_subject"
                                    label="Betreff"
                                    :value="old('member_exit_mail_subject', $tenant->member_exit_mail_subject)"
                                    help="Beispiel: Bestaetigung deines Austritts bei {verein}" />
                    </div>

                    @include('tenant.partials.exit-mail-placeholders')

                    <div>
                        <label for="member_exit_mail_body" class="block text-sm font-medium text-slate-700">Mailtext</label>
                        <textarea name="member_exit_mail_body"
                                  id="member_exit_mail_body"
                                  rows="10"
                                  class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm">{{ old('member_exit_mail_body', $tenant->member_exit_mail_body) }}</textarea>
                        <p class="mt-2 text-xs text-slate-500">
                            Die Mail darf herzlich, klar und persoenlich klingen. Clubano setzt die Platzhalter automatisch ein.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Speichern --}}
        <div class="text-right pt-4">
            <button type="submit"
                    class="bg-[#2954A3] hover:bg-[#1E3F7F] text-white font-semibold px-8 py-3 rounded-xl shadow-md transition duration-200">
                💾 Speichern
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="/tinymce/tinymce.min.js"></script>
<script>
    tinymce.init({
        selector: '#member_exit_mail_body',
        license_key: 'gpl',
        height: 360,
        menubar: false,
        plugins: 'lists link table code autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link table | removeformat code',
        branding: false,
        promotion: false,
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
    });

    window.insertIntoExitMailEditor = function (text) {
        if (window.tinymce?.get('member_exit_mail_body')) {
            window.tinymce.get('member_exit_mail_body').insertContent(text);
            return;
        }

        const textarea = document.getElementById('member_exit_mail_body');
        if (!textarea) {
            return;
        }

        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
        textarea.focus();
        textarea.setSelectionRange(start + text.length, start + text.length);
    };
</script>
@endpush
