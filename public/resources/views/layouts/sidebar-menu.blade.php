<div class="p-6 border-b">
    <h1 class="text-2xl font-bold text-indigo-700">🏛 Clubano</h1>
</div>

<nav class="flex-1 px-4 py-4 space-y-4 text-sm">
    <div>
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Navigation</h2>
        <ul class="space-y-1">
            <li>
                <a href="{{ route('dashboard') }}"
                   class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 {{ request()->routeIs('dashboard') ? 'bg-indigo-100 text-indigo-800 font-semibold' : 'text-gray-700' }}">
                    🏠 <span class="ml-2">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="{{ route('members.index') }}"
                   class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 {{ request()->routeIs('members.index') ? 'bg-indigo-100 text-indigo-800 font-semibold' : 'text-gray-700' }}">
                    👥 <span class="ml-2">Mitglieder</span>
                </a>
            </li>
            <li>
                <a href="{{ route('tenant.show') }}"
                   class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 {{ request()->routeIs('tenant.show') ? 'bg-indigo-100 text-indigo-800 font-semibold' : 'text-gray-700' }}">
                    🏢 <span class="ml-2">Verein</span>
                </a>
            </li>
        </ul>
    </div>

    <div>
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Finanzen</h2>
        <ul class="space-y-1">
            <li><a href="{{ route('accounts.index') }}" class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 text-gray-700">📒 <span class="ml-2">Kontenplan</span></a></li>
            <li><a href="{{ route('transactions.index') }}" class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 text-gray-700">📑 <span class="ml-2">Buchungen</span></a></li>
            <li><a href="{{ route('transactions.summary') }}" class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 text-gray-700">📈 <span class="ml-2">Einnahmen & Ausgaben</span></a></li>
        </ul>
    </div>

    <div>
        <h2 class="text-xs font-semibold uppercase text-gray-500 mb-1">Einstellungen</h2>
        <ul class="space-y-1">
            <li><a href="{{ route('memberships.index') }}" class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 text-gray-700">💳 <span class="ml-2">Mitgliedschaften</span></a></li>
            <li><a href="{{ route('profile.edit') }}" class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 text-gray-700">🙍 <span class="ml-2">Profil</span></a></li>
            <li><a href="{{ route('import.mitglieder') }}" class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 text-gray-700">📥 <span class="ml-2">Mitgliederimport</span></a></li>
            <li><a href="{{ route('roles.edit') }}" class="flex items-center px-3 py-2 rounded-md hover:bg-indigo-50 text-gray-700">🔐 <span class="ml-2">Rollen</span></a></li>
        </ul>
    </div>

    <div class="pt-4 border-t">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex items-center w-full text-left px-3 py-2 text-red-600 hover:bg-red-100 rounded-md">
                🚪 <span class="ml-2">Logout</span>
            </button>
        </form>
    </div>
</nav>
