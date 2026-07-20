<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Clubano')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 flex h-screen">

    {{-- Sidebar --}}
    <aside class="w-64 bg-white shadow-md hidden md:block">
        <div class="p-6 border-b">
            <h1 class="text-2xl font-bold text-blue-600">Clubano</h1>
        </div>
        <nav class="p-4 space-y-2">
            <a href="{{ route('dashboard') }}" class="block px-4 py-2 rounded hover:bg-blue-100 {{ request()->routeIs('dashboard') ? 'bg-blue-200 font-semibold' : '' }}">🏠 Dashboard</a>
            <a href="{{ route('tenant.edit') }}" class="block px-4 py-2 rounded hover:bg-blue-100 {{ request()->is('verein*') ? 'bg-blue-200 font-semibold' : '' }}">🏢 Verein</a>
            <a href="{{ route('members.index') }}" class="block px-4 py-2 rounded hover:bg-blue-100 {{ request()->is('members*') ? 'bg-blue-200 font-semibold' : '' }}">👥 Mitglieder</a>
            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 rounded hover:bg-blue-100">⚙️ Profil</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full text-left px-4 py-2 mt-4 rounded hover:bg-red-100 text-red-600">🚪 Abmelden</button>
            </form>
        </nav>
    </aside>

    {{-- Inhalt --}}
    <main class="flex-1 overflow-y-auto p-6">
        @yield('content')
    </main>

</body>
</html>
