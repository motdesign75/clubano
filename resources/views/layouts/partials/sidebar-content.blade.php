<div class="p-6 text-xl font-semibold text-indigo-800 border-b border-indigo-100">
    🏛 Clubano
</div>

<nav class="p-4 text-sm text-indigo-800 space-y-4">
    <ul class="space-y-2">
        <li>
            <a href="{{ route('dashboard') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                🏠 Dashboard
            </a>
        </li>
        <li>
            <a href="{{ route('tenant.show') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('tenant.show')) aria-current="page" @endif>
                🏢 Verein
            </a>
        </li>
        <li>
            <a href="{{ route('members.index') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('members.index')) aria-current="page" @endif>
                👥 Mitglieder
            </a>
        </li>
    </ul>

    <hr class="border-indigo-100">

    <h2 class="text-xs font-semibold uppercase text-indigo-500 pl-1">💰 Finanzen</h2>
    <ul class="space-y-2">
        <li>
            <a href="{{ route('accounts.index') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('accounts.index')) aria-current="page" @endif>
                📒 Kontenplan
            </a>
        </li>
        <li>
            <a href="{{ route('transactions.index') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('transactions.*')) aria-current="page" @endif>
                📑 Buchungen
            </a>
        </li>
        <li>
            <a href="{{ route('transactions.summary') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('transactions.summary')) aria-current="page" @endif>
                📈 Einnahmen & Ausgaben
            </a>
        </li>
    </ul>

    <hr class="border-indigo-100">

    <h2 class="text-xs font-semibold uppercase text-indigo-500 pl-1">⚙️ Einstellungen</h2>
    <ul class="space-y-2">
        <li>
            <a href="{{ route('memberships.index') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('memberships.index')) aria-current="page" @endif>
                💳 Mitgliedschaften
            </a>
        </li>
        <li>
            <a href="{{ route('profile.edit') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('profile.edit')) aria-current="page" @endif>
                🙍 Profil
            </a>
        </li>
        <li>
            <a href="{{ route('import.index') }}"
               class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
               @if (request()->routeIs('import.*')) aria-current="page" @endif>
                📥 Import
            </a>
        </li>
        @if(auth()->user()?->isSuperAdmin())
            <li>
                <a href="{{ route('roles.edit') }}"
                   class="block px-3 py-2 rounded hover:bg-indigo-100 transition"
                   @if (request()->routeIs('roles.edit')) aria-current="page" @endif>
                    🔐 Rollen
                </a>
            </li>
        @endif
    </ul>

    <hr class="border-indigo-100">

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
                class="w-full text-left px-3 py-2 rounded text-red-600 hover:bg-red-50 transition">
            🚪 Logout
        </button>
    </form>
</nav>
