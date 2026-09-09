@extends('layouts.app')

@section('content')
<div class="space-y-8">
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
            <div class="font-semibold text-rose-950">Bitte kurz prüfen.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-900">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="overflow-hidden rounded-[28px] bg-gradient-to-br from-slate-950 via-teal-950 to-slate-800 px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Automatische Mails</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Persönliche Nachrichten, ohne jeden Anlass neu anzufassen.</h1>
                <p class="mt-3 text-sm leading-6 text-slate-200 sm:text-base">
                    Aktiviere einen Anlass, gestalte den Text einmal und Clubano versendet die Mail passend zum Datum. Du behältst Kontrolle, die Mitglieder bekommen trotzdem eine persönliche Nachricht.
                </p>
            </div>
            <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 text-sm backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Geburtstage erreichbar</div>
                <div class="mt-2 text-3xl font-semibold">{{ $birthdayCandidates }}</div>
                <div class="mt-1 text-slate-300">aktive Mitglieder mit E-Mail und Geburtstag</div>
            </div>
        </div>
    </section>

    @foreach($settings as $occasion => $entry)
        @php($setting = $entry['setting'])
        <form method="POST" action="{{ route('automated-mails.update', $occasion) }}" class="grid gap-6 xl:grid-cols-3">
            @csrf
            @method('PUT')

            <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Anlass</div>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $entry['label'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Wird einmal pro Mitglied und Geburtstag versendet. Archivierte und ausgetretene Mitglieder werden nicht berücksichtigt.
                        </p>
                    </div>
                    <label class="inline-flex w-fit items-center gap-3 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-800">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled)) class="rounded border-slate-300 text-slate-950 focus:ring-slate-500">
                        Automatik aktiv
                    </label>
                </div>

                <div class="mt-6 grid gap-5 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label for="subject-{{ $occasion }}" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Betreff</label>
                        <input id="subject-{{ $occasion }}" type="text" name="subject" value="{{ old('subject', $setting->subject) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300" required>
                    </div>
                    <div>
                        <label for="send_time-{{ $occasion }}" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Versandzeit</label>
                        <input id="send_time-{{ $occasion }}" type="time" name="send_time" value="{{ old('send_time', $setting->send_time ?: '09:00') }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300" required>
                    </div>
                    <div>
                        <label for="days_before-{{ $occasion }}" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Wann senden?</label>
                        <select id="days_before-{{ $occasion }}" name="days_before" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                            @foreach([0 => 'am Geburtstag', 1 => '1 Tag vorher', 3 => '3 Tage vorher', 7 => '7 Tage vorher', 14 => '14 Tage vorher'] as $days => $label)
                                <option value="{{ $days }}" @selected((int) old('days_before', $setting->days_before) === $days)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
                            Platzhalter: <span class="font-mono">@{{ vorname }}</span>, <span class="font-mono">@{{ name }}</span>, <span class="font-mono">@{{ alter }}</span>, <span class="font-mono">@{{ geburtstag }}</span>, <span class="font-mono">@{{ verein }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <label for="body_html-{{ $occasion }}" class="sr-only">E-Mail-Text</label>
                    <textarea id="body_html-{{ $occasion }}" name="body_html" rows="16" class="automated-mail-editor w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">{{ old('body_html', $setting->body_html) }}</textarea>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('mail.create') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Einzelne E-Mail schreiben
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Automatik speichern
                    </button>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Testmail</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Erst ansehen, dann laufen lassen</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Sende dir eine Testmail mit den aktuellen Platzhaltern. Speichere Änderungen vorher, damit der Test genau diese Version nutzt.
                    </p>
                    <div class="mt-4 space-y-3">
                        <input type="email" name="test_email" form="test-mail-{{ $occasion }}" value="{{ auth()->user()->email }}" class="w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300" placeholder="deine@email.de">
                        <button type="submit" form="test-mail-{{ $occasion }}" class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Testmail senden
                        </button>
                    </div>
                </section>

                <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sicherheit</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Kein Blindflug</h2>
                    <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <p>Clubano versendet nur an aktive Mitglieder mit E-Mail-Adresse und Geburtstag.</p>
                        <p>Pro Mitglied und Geburtstag wird nur eine Mail verschickt.</p>
                        <p>Der Serverjob kann stündlich laufen und holt fällige Mails automatisch nach.</p>
                    </div>
                </section>
            </aside>
        </form>

        <form id="test-mail-{{ $occasion }}" method="POST" action="{{ route('automated-mails.test', $occasion) }}" class="hidden">
            @csrf
        </form>
    @endforeach

    <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Protokoll</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Zuletzt automatisch versendet</h2>
            </div>
            <div class="text-sm text-slate-500">{{ $recentDeliveries->count() }} Einträge</div>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($recentDeliveries as $delivery)
                <div class="grid gap-3 px-5 py-4 text-sm sm:grid-cols-[1fr_auto] sm:items-center sm:px-6">
                    <div>
                        <div class="font-semibold text-slate-950">{{ $delivery->recipient_name ?: $delivery->recipient_email }}</div>
                        <div class="mt-1 text-slate-500">{{ $delivery->subject }} · {{ $delivery->occasion_date?->format('d.m.Y') }}</div>
                    </div>
                    <div class="rounded-full px-3 py-1 text-xs font-semibold {{ $delivery->status === 'sent' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                        {{ $delivery->status === 'sent' ? 'versendet' : 'Fehler' }}
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-sm text-slate-500 sm:px-6">Noch keine automatischen Mails versendet.</div>
            @endforelse
        </div>
    </section>
</div>

@push('scripts')
    <script src="/tinymce/tinymce.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            tinymce.init({
                selector: '.automated-mail-editor',
                license_key: 'gpl',
                height: 520,
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
                content_style: 'body{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;line-height:1.65;color:#0f172a;} h2,h3{line-height:1.25;margin:1.2em 0 .5em;} p{margin:.7em 0;} img{max-width:100%;height:auto;}',
            });
        });
    </script>
@endpush
@endsection
