<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Clubano') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/clubano-icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('images/clubano-icon.svg') }}">

    @include('layouts.partials.google-analytics')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gradient-to-br from-gray-50 to-blue-50">

    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-12">

        <!-- ✅ LOGO (HIER GEHÖRT ES HIN) -->
        <div class="mb-8 text-center">
            <a href="/">
                <img 
                    src="{{ asset('images/clubano.svg') }}" 
                    alt="Clubano"
                    class="h-24 w-auto mx-auto"
                >
            </a>
        </div>

        <!-- ✅ CARD -->
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-xl p-8">
                {{ $slot }}
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-xs text-gray-400">
            © {{ date('Y') }} Clubano · Sicher · DSGVO-konform
        </div>

    </div>

</body>
</html>
