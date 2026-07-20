<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Clubano') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/clubano-icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('images/clubano-icon.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="min-h-screen">
        {{-- Navigation --}}
        @include('layouts.navigation')

        {{-- Header --}}
        @isset($header)
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        {{-- Hauptinhalt --}}
        <main class="py-6 px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
