<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Clubano' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/clubano-icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('images/clubano-icon.svg') }}">
    @if(!empty($robots))
        <meta name="robots" content="{{ $robots }}">
    @endif
    @include('layouts.partials.google-analytics')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass ?? 'min-h-screen bg-slate-50 text-slate-900' }}">
    @yield('content')
</body>
</html>
