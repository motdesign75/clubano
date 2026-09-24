<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stammdaten prüfen</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <section class="rounded-3xl bg-white p-6 shadow-sm sm:p-8">
            <div class="border-b border-slate-200 pb-6">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ $tenant->name }}</div>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Bitte prüfen Sie Ihre Stammdaten</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Hier sehen Sie die bei uns gespeicherten Daten. Sie können fehlende oder falsche Angaben direkt korrigieren. Die Änderungen werden anschließend geprüft und erst danach übernommen.
                </p>
            </div>

            <form method="POST" action="{{ $submitUrl }}" class="mt-6 space-y-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($fields as $name => $field)
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-800">{{ $field['label'] }}</span>
                            <input type="{{ $field['type'] }}"
                                   name="{{ $name }}"
                                   value="{{ old($name, $data[$name] ?? '') }}"
                                   class="mt-2 block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error($name)
                                <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-800">Hinweis zu den Änderungen</span>
                    <textarea name="change_note" rows="4" class="mt-2 block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional, z.B. Umzug, neue Telefonnummer oder Korrekturhinweis.">{{ old('change_note') }}</textarea>
                </label>

                <button type="submit" class="inline-flex w-full items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    Angaben senden
                </button>
            </form>
        </section>
    </main>
</body>
</html>
