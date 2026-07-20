<aside class="hidden md:flex md:flex-col w-64 h-screen bg-white border-r border-gray-200 fixed z-40">
    <div class="p-6">
        <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-800">
            Clubano
        </a>
    </div>
    <nav class="flex-1 px-4 space-y-2">
        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            {{ __('Dashboard') }}
        </x-nav-link>
        <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
            {{ __('Profil') }}
        </x-nav-link>
        <!-- Weitere Links hier -->
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="p-4">
        @csrf
        <x-nav-link :href="route('logout')"
            onclick="event.preventDefault(); this.closest('form').submit();">
            {{ __('Abmelden') }}
        </x-nav-link>
    </form>
</aside>

<!-- Hamburger für Mobilgeräte -->
<div class="md:hidden bg-white border-b border-gray-100 px-4 py-3 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-800">
        Clubano
    </a>
    <button @click="open = !open" class="text-gray-500 focus:outline-none">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16" />
            <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<!-- Mobile Menü -->
<div x-show="open" class="md:hidden bg-white border-b border-gray-200 px-4 py-2 space-y-2">
    <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
        {{ __('Dashboard') }}
    </x-responsive-nav-link>
    <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
        {{ __('Profil') }}
    </x-responsive-nav-link>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-responsive-nav-link :href="route('logout')"
            onclick="event.preventDefault(); this.closest('form').submit();">
            {{ __('Abmelden') }}
        </x-responsive-nav-link>
    </form>
</div>
