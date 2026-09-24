<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vielen Dank</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-8 sm:px-6">
        <section class="w-full rounded-3xl bg-white p-6 text-center shadow-sm sm:p-8">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">{{ $tenant->name }}</div>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Vielen Dank</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                @if($changesCount > 0)
                    Ihre Änderungen wurden übermittelt und werden jetzt geprüft. Erst nach der Prüfung werden die Stammdaten aktualisiert.
                @else
                    Ihre Rückmeldung wurde gespeichert. Es wurden keine Änderungen an den gespeicherten Daten gemeldet.
                @endif
            </p>
        </section>
    </main>
</body>
</html>
