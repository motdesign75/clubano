<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Produktupdates abbestellt</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-950 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-700">Clubano</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal">Produktupdates abbestellt</h1>
            <p class="mt-4 text-sm leading-6 text-slate-600">
                Für {{ $user->email }} werden keine Betreiber-Produktupdates mehr per E-Mail versendet.
                Wichtige Hinweise zu Sicherheit, Vertrag, Abrechnung oder Datenschutz bleiben davon unberührt.
            </p>
            <a href="{{ config('app.url') }}" class="mt-6 inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Zu Clubano
            </a>
        </section>
    </main>
</body>
</html>
