@extends('layouts.app')

@section('title', 'Gutscheinvorlage')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Gutscheinvorlage</div>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Eigenes Design, sicherer Code.</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300 sm:text-base">
            Lade das Vereinsdesign als Bild hoch. Clubano erzeugt daraus für jeden Gutschein ein PDF und setzt Code, Wert und QR-Code automatisch darüber.
        </p>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte prüfe die markierten Felder.
        </div>
    @endif

    <form method="POST" action="{{ route('vouchers.settings.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @method('PUT')

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Design</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Bildvorlage</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Lade ein PNG oder JPG in dem Format hoch, in dem der Gutschein später erscheinen soll. Clubano übernimmt das Seitenverhältnis automatisch und verzerrt die Vorlage nicht.
                </p>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
                    <strong class="text-slate-900">Empfehlung:</strong> Klassischer Gutschein 2000 × 1000 px. A4 quer 3508 × 2480 px. Der Code sollte im Design einen ruhigen Bereich freilassen.
                </div>
            </div>

            <div class="mt-6">
                <label for="voucher_template" class="mb-1 block text-sm font-medium text-slate-700">Vorlagenbild</label>
                <input id="voucher_template" type="file" name="voucher_template" accept="image/*" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-slate-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                @error('voucher_template')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>

            @if($tenant->voucher_template_path)
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($tenant->voucher_template_path) }}"
                        alt="Aktuelle Gutscheinvorlage"
                        class="w-full object-contain"
                        style="aspect-ratio: {{ $tenant->voucher_template_width && $tenant->voucher_template_height ? $tenant->voucher_template_width . ' / ' . $tenant->voucher_template_height : '2 / 1' }};"
                    >
                    @if($tenant->voucher_template_width && $tenant->voucher_template_height)
                        <div class="border-t border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Aktuelle Vorlage: {{ $tenant->voucher_template_width }} × {{ $tenant->voucher_template_height }} px
                        </div>
                    @endif
                </div>

                <label class="mt-4 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950">
                    <input type="checkbox" name="remove_template" value="1" class="mt-0.5 rounded border-rose-300 text-rose-700 focus:ring-rose-300">
                    <span>Aktuelle Vorlage entfernen</span>
                </label>
            @endif

            <div class="mt-7 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="voucher_code_position" class="mb-1 block text-sm font-medium text-slate-700">Position des Codes</label>
                    <select id="voucher_code_position" name="voucher_code_position" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                        @foreach($positions as $value => $label)
                            <option value="{{ $value }}" @selected(old('voucher_code_position', $tenant->voucher_code_position ?: 'bottom-right') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('voucher_code_position')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="voucher_code_color" class="mb-1 block text-sm font-medium text-slate-700">Textfarbe</label>
                    <input id="voucher_code_color" type="color" name="voucher_code_color" value="{{ old('voucher_code_color', $tenant->voucher_code_color ?: '#0f172a') }}" class="h-11 w-full rounded-xl border-slate-300 p-1 shadow-sm">
                    @error('voucher_code_color')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="mt-5 flex items-start gap-3 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-950">
                <input type="checkbox" name="voucher_show_qr" value="1" @checked(old('voucher_show_qr', $tenant->voucher_show_qr ?? true)) class="mt-0.5 rounded border-blue-300 text-blue-700 focus:ring-blue-300">
                <span>
                    <span class="block font-semibold">QR-Code anzeigen</span>
                    <span class="mt-1 block text-blue-900/80">Der QR-Code enthält den Gutscheincode und erleichtert spätere Prüfung oder Scan-Funktionen.</span>
                </span>
            </label>
        </section>

        <aside class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Mail</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Versandtext</h2>

                <div class="mt-4">
                    <label for="voucher_mail_subject" class="mb-1 block text-sm font-medium text-slate-700">Betreff</label>
                    <input id="voucher_mail_subject" type="text" name="voucher_mail_subject" value="{{ old('voucher_mail_subject', $tenant->voucher_mail_subject) }}" placeholder="Dein Gutschein von {{ $tenant->name }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    @error('voucher_mail_subject')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <label for="voucher_mail_body" class="mb-1 block text-sm font-medium text-slate-700">Nachricht</label>
                    <textarea id="voucher_mail_body" name="voucher_mail_body" rows="8" class="w-full rounded-xl border-slate-300 text-sm leading-6 shadow-sm focus:border-slate-500 focus:ring-slate-300" placeholder="<p>Guten Tag,</p><p>anbei senden wir den Gutschein @{{ code }}.</p>">{{ old('voucher_mail_body', $tenant->voucher_mail_body) }}</textarea>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Platzhalter: @{{ code }}, @{{ wert }}, @{{ empfaenger }}, @{{ widmung }}, @{{ verein }}</p>
                    @error('voucher_mail_body')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
            </section>

            <button type="submit" class="inline-flex w-full min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Vorlage speichern
            </button>

            <a href="{{ route('vouchers.index') }}" class="inline-flex w-full min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Zurück zu Gutscheinen
            </a>
        </aside>
    </form>
</div>
@endsection
