<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ open: false }" x-cloak class="h-full bg-gray-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Clubano'))</title>

    {{-- Vite: CSS & JS (inkl. Livewire v3 & AlpineJS via app.js) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire Styles --}}
    @livewireStyles

    @stack('head')
</head>
<body class="h-full antialiased font-sans text-gray-800">

<!-- Mobiler Header -->
<header class="sm:hidden fixed top-0 left-0 right-0 z-40 bg-white shadow-md px-4 py-3 flex justify-between items-center">
    <div class="text-xl font-semibold">Clubano</div>
    <button @click="open = !open" aria-label="Menü öffnen">
        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" stroke-width="2"
             viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</header>

<!-- Mobile Sidebar -->
<div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 transform -translate-x-full"
     x-transition:enter-end="opacity-100 transform translate-x-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 transform translate-x-0"
     x-transition:leave-end="opacity-0 transform -translate-x-full"
     class="sm:hidden fixed top-0 left-0 z-50 w-64 h-full bg-white shadow-lg overflow-y-auto"
     x-cloak>
    <div class="relative h-full">
        <button @click="open = false" class="absolute top-3 right-3 text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <div class="p-4 mt-10">
            @include('layouts.sidebar')
        </div>
    </div>
</div>

<!-- Overlay bei mobilem Menü -->
<div x-show="open"
     x-transition.opacity
     class="sm:hidden fixed inset-0 bg-black bg-opacity-25 z-40"
     x-cloak
     @click="open = false">
</div>

<!-- Desktop Layout -->
<div class="flex h-screen overflow-hidden">

    <!-- Sidebar (Desktop) -->
    <aside class="hidden sm:flex sm:flex-col sm:w-64 bg-white border-r border-indigo-100 shadow z-30 overflow-y-auto">
        @include('layouts.sidebar')
    </aside>

    <!-- Content -->
    <main class="flex-1 w-full overflow-y-auto sm:pt-6 px-4 pt-[56px]">
        @yield('content')
    </main>
</div>

<!-- Feedback Button + Modal -->
@auth
    <button id="feedbackToggle"
        class="fixed bottom-6 right-6 z-50 bg-[#2954A3] text-white px-4 py-2 rounded-full shadow-lg hover:bg-[#1E3F7F] transition">
        🗣️ Feedback geben
    </button>

    <!-- Modal -->
    <div id="feedbackModal" class="fixed inset-0 bg-black bg-opacity-40 hidden z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-xl w-full max-w-lg relative shadow-xl">
            <button onclick="closeFeedback()" class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl">&times;</button>
            <h2 class="text-xl font-bold mb-4 text-gray-800">Dein Feedback</h2>

            <form method="POST" action="{{ route('feedback.store') }}">
                @csrf

                <label for="category" class="block font-medium text-gray-700 mb-1">Kategorie</label>
                <select name="category" id="category"
                        class="w-full border border-gray-300 rounded-lg p-3 mb-4 focus:ring-2 focus:ring-[#2954A3] focus:outline-none"
                        required>
                    <option value="Fehler">🐞 Fehler</option>
                    <option value="Verbesserung">🔧 Verbesserung</option>
                    <option value="Allgemein">💬 Allgemein</option>
                </select>

                <input type="hidden" name="view" value="{{ Route::currentRouteName() }}">
                <input type="hidden" name="url" value="{{ url()->full() }}">

                <textarea name="message" required rows="5"
                          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[#2954A3] focus:outline-none"
                          placeholder="Was können wir verbessern? Oder hast du einen Fehler entdeckt?"></textarea>

                <button type="submit"
                        class="mt-4 bg-[#2954A3] text-white px-4 py-2 rounded-lg hover:bg-[#1E3F7F] transition">
                    ✅ Feedback absenden
                </button>
            </form>
        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('feedbackToggle');
        const modal = document.getElementById('feedbackModal');

        toggleBtn.addEventListener('click', () => {
            modal.classList.remove('hidden');
        });

        function closeFeedback() {
            modal.classList.add('hidden');
        }
    </script>
@endauth

{{-- Livewire Scripts (bei v3 nötig!) --}}
@livewireScripts
@stack('scripts')
</body>
</html>
