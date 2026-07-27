@php
    $tenant = app('currentTenant') ?? auth()->user()?->tenant;
    $user = auth()->user();

    $primaryNav = [
        [
            'label' => 'Dashboard',
            'route' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'icon' => 'home',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Mitglieder',
            'route' => route('members.index'),
            'active' => request()->routeIs('members.*'),
            'icon' => 'users',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Kalender',
            'route' => route('events.index'),
            'active' => request()->routeIs('events.*') || request()->routeIs('event-categories.*'),
            'icon' => 'calendar',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Finanzen',
            'route' => route('transactions.index'),
            'active' => request()->routeIs('accounts.*')
                || request()->routeIs('transactions.*')
                || request()->routeIs('donations.*')
                || request()->routeIs('invoices.*')
                || request()->routeIs('payments.*'),
            'icon' => 'banknotes',
            'minRole' => 'Admin',
        ],
    ];

    $workNav = [
        [
            'label' => 'Formulare',
            'route' => route('forms.index'),
            'active' => request()->routeIs('forms.*'),
            'icon' => 'link',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Kontakte',
            'route' => route('contacts.index'),
            'active' => request()->routeIs('contacts.*'),
            'icon' => 'identification',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Projekte',
            'route' => route('projects.index'),
            'active' => request()->routeIs('projects.*'),
            'icon' => 'rectangle-stack',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Aufgaben',
            'route' => route('tasks.index'),
            'active' => request()->routeIs('tasks.*'),
            'icon' => 'check-circle',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Dokumente',
            'route' => route('documents.index'),
            'active' => request()->routeIs('documents.*'),
            'icon' => 'archive-box',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Protokolle',
            'route' => route('protocols.index'),
            'active' => request()->routeIs('protocols.*'),
            'icon' => 'document-duplicate',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Kommunikation',
            'route' => route('templates.index'),
            'active' => request()->routeIs('templates.*') || request()->routeIs('mail.*') || request()->routeIs('letters.*'),
            'icon' => 'paper-airplane',
            'minRole' => 'Mitarbeiter',
        ],
    ];

    $financeNav = [
        [
            'label' => 'Konten & Kassen',
            'hint' => 'Bank, Kasse und Buchhaltung einrichten',
            'route' => route('accounts.index'),
            'active' => request()->routeIs('accounts.*'),
            'icon' => 'clipboard-document-list',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Kassenbuch',
            'hint' => 'Barbewegungen erfassen und pruefen',
            'route' => route('transactions.cashbook'),
            'active' => request()->routeIs('transactions.cashbook'),
            'icon' => 'banknotes',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Geldbewegungen',
            'hint' => 'Alles sehen, was rein- oder rausgeht',
            'route' => route('transactions.index'),
            'active' => request()->routeIs('transactions.index') || request()->routeIs('transactions.create') || request()->routeIs('transactions.edit') || request()->routeIs('transactions.cancel*'),
            'icon' => 'document-text',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Auswertungen',
            'hint' => 'EÜR, Journal und Jahresabschluss vorbereiten',
            'route' => route('transactions.corporation-tax'),
            'active' => request()->routeIs('transactions.summary') || request()->routeIs('transactions.eur') || request()->routeIs('transactions.journal*') || request()->routeIs('transactions.corporation-tax'),
            'icon' => 'chart-bar',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Spenden',
            'hint' => 'Spenden erfassen und Bestätigungen erstellen',
            'route' => route('donations.index'),
            'active' => request()->routeIs('donations.*'),
            'icon' => 'gift',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Rechnungen',
            'hint' => 'Rechnungen schreiben und Zahlungen verfolgen',
            'route' => route('invoices.index'),
            'active' => request()->routeIs('invoices.*'),
            'icon' => 'receipt-percent',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Haushaltsplan',
            'hint' => 'Planen, vergleichen und dem Vorstand zeigen',
            'route' => route('budgets.index'),
            'active' => request()->routeIs('budgets.*'),
            'icon' => 'presentation-chart-line',
            'minRole' => 'Admin',
        ],
    ];

    $calendarNav = [
        [
            'label' => 'Kalenderübersicht',
            'hint' => 'Termine sehen und den Monat planen',
            'route' => route('events.index'),
            'active' => request()->routeIs('events.index'),
            'icon' => 'calendar',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Aktivität planen',
            'hint' => 'Training, Spiel, Sitzung oder Einsatz anlegen',
            'route' => route('events.create'),
            'active' => request()->routeIs('events.create'),
            'icon' => 'plus',
            'minRole' => 'Mitarbeiter',
        ],
        [
            'label' => 'Aktivitätsarten',
            'hint' => 'Kategorien, Zielgruppen und Standards steuern',
            'route' => route('event-categories.index'),
            'active' => request()->routeIs('event-categories.*'),
            'icon' => 'swatch',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Anwesenheit',
            'hint' => 'Teilnahme und Pflichtstunden auswerten',
            'route' => route('events.attendance.report'),
            'active' => request()->routeIs('events.attendance.report'),
            'icon' => 'chart-bar',
            'minRole' => 'Mitarbeiter',
        ],
        [
            'label' => 'Aushang',
            'hint' => 'Termine für Vereinsheim oder Infofläche drucken',
            'route' => route('events.poster'),
            'active' => request()->routeIs('events.poster') || request()->routeIs('events.poster.print') || request()->routeIs('events.poster.pdf'),
            'icon' => 'printer',
            'minRole' => 'Mitarbeiter',
        ],
    ];

    $organizationNav = [
        [
            'label' => 'Verein',
            'route' => route('tenant.show'),
            'active' => request()->routeIs('tenant.*'),
            'icon' => 'building-office',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Benutzer',
            'route' => route('users.index'),
            'active' => request()->routeIs('users.*'),
            'icon' => 'user-group',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Mitgliedschaften',
            'route' => route('memberships.index'),
            'active' => request()->routeIs('memberships.*'),
            'icon' => 'credit-card',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Eigene Felder',
            'route' => route('custom-fields.index'),
            'active' => request()->routeIs('custom-fields.*'),
            'icon' => 'puzzle-piece',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Tags',
            'route' => route('tags.index'),
            'active' => request()->routeIs('tags.*'),
            'icon' => 'tag',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Import',
            'route' => route('import.mitglieder'),
            'active' => request()->routeIs('import.mitglieder*'),
            'icon' => 'cloud-arrow-down',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Maileinstellungen',
            'route' => url('/settings/email'),
            'active' => request()->is('settings/email'),
            'icon' => 'envelope',
            'minRole' => 'Admin',
        ],
    ];

    $personalNav = [
        [
            'label' => 'Profil',
            'route' => route('profile.edit'),
            'active' => request()->routeIs('profile.*'),
            'icon' => 'user',
            'minRole' => 'Lesen',
        ],
    ];

    $systemNav = [];

    if ($user?->isSuperAdmin()) {
        $systemNav[] = [
            'label' => 'Admin-Cockpit',
            'route' => route('admin.dashboard'),
            'active' => request()->routeIs('admin.dashboard') || request()->routeIs('admin.tenants.*'),
            'icon' => 'shield-check',
        ];
        $systemNav[] = [
            'label' => 'Betreiberkonto',
            'route' => route('admin.account'),
            'active' => request()->routeIs('admin.account'),
            'icon' => 'user',
        ];
        if (filled($user->tenant_id)) {
            $systemNav[] = [
                'label' => 'Rollen',
                'route' => route('roles.edit'),
                'active' => request()->routeIs('roles.*'),
                'icon' => 'lock-closed',
            ];
        }
    }

    $navGroups = $user?->isSuperAdmin() && blank($user->tenant_id)
        ? ['System' => $systemNav]
        : [
            'Start' => $primaryNav,
            'Arbeit' => $workNav,
            'Verwaltung' => $organizationNav,
            'System' => $systemNav,
        ];

    $navGroups = collect($navGroups)
        ->map(function ($items) use ($user) {
            return collect($items)
                ->filter(fn ($item) => ($user?->hasRoleAtLeast($item['minRole'] ?? 'Lesen')) ?? false)
                ->values()
                ->all();
        })
        ->filter(fn ($items) => !empty($items))
        ->all();

    $calendarNav = collect($calendarNav)
        ->filter(fn ($item) => ($user?->hasRoleAtLeast($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();

    $personalNav = collect($personalNav)
        ->filter(fn ($item) => ($user?->hasRoleAtLeast($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();
@endphp

<div :class="collapsed ? 'px-3 py-4' : 'px-5 py-5'" class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur transition-all duration-200">
    <div :class="collapsed ? 'justify-center' : 'justify-between'" class="flex items-start gap-3">
        <div class="min-w-0">
            <div :class="collapsed ? 'justify-center' : ''" class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-sm">
                    <x-heroicon-o-building-office-2 class="h-5 w-5" />
                </span>
                <div x-show="!collapsed">
                    <div class="text-base font-semibold tracking-tight text-slate-950">Clubano</div>
                    <div class="text-xs text-slate-400">Einfacher Vereinsalltag</div>
                </div>
            </div>

            @if($tenant)
                <div x-show="!collapsed" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                    <div class="truncate text-sm font-semibold text-slate-900">{{ $tenant->name }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        @if($tenant->hasComplimentaryAccess())
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">
                                {{ $tenant->license_mode_label }}
                            </span>
                        @elseif($tenant->onTrial())
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">
                                Testphase
                            </span>
                        @elseif($tenant->subscribed('default'))
                            <span class="rounded-full bg-sky-100 px-2 py-0.5 font-semibold text-sky-800">
                                Lizenz aktiv
                            </span>
                        @endif

                        @if($tenant->city)
                            <span class="text-slate-500">{{ $tenant->city }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <button type="button"
                @click="toggle()"
                :title="collapsed ? 'Sidebar ausklappen' : 'Sidebar einklappen'"
                class="hidden rounded-xl border border-slate-200 bg-white p-2 text-slate-500 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 sm:inline-flex">
            <x-heroicon-o-chevron-double-left x-show="!collapsed" class="h-4 w-4" />
            <x-heroicon-o-chevron-double-right x-show="collapsed" class="h-4 w-4" />
        </button>
    </div>
</div>

<nav :class="collapsed ? 'space-y-5 px-2 py-4' : 'space-y-6 px-4 py-5'" class="text-sm text-slate-700 transition-all duration-200" aria-label="Hauptnavigation">
    @foreach($navGroups as $groupLabel => $items)
        <section>
            <h2 x-show="!collapsed" class="px-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                {{ $groupLabel }}
            </h2>
            <ul class="mt-2 space-y-1.5">
                @foreach($items as $item)
                    <li>
                        <a href="{{ $item['route'] }}"
                           @if($item['active']) aria-current="page" @endif
                           title="{{ $item['label'] }}"
                           :class="collapsed ? 'justify-center px-2' : 'justify-between px-3'"
                           class="group flex items-center gap-3 rounded-2xl py-2.5 transition-all duration-150 {{ $item['active'] ? 'bg-slate-950 text-white shadow-sm' : 'hover:bg-slate-100 text-slate-700' }}">
                            <span :class="collapsed ? 'justify-center' : ''" class="flex min-w-0 items-center gap-3">
                                @php($iconColor = $item['active'] ? 'text-white' : 'text-slate-400 group-hover:text-slate-700')
                                @switch($item['icon'])
                                    @case('home')
                                        <x-heroicon-o-home class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('users')
                                        <x-heroicon-o-users class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('calendar')
                                        <x-heroicon-o-calendar class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('plus')
                                        <x-heroicon-o-plus class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('swatch')
                                        <x-heroicon-o-swatch class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('printer')
                                        <x-heroicon-o-printer class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('link')
                                        <x-heroicon-o-link class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('identification')
                                        <x-heroicon-o-identification class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('rectangle-stack')
                                        <x-heroicon-o-rectangle-stack class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('check-circle')
                                        <x-heroicon-o-check-circle class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('archive-box')
                                        <x-heroicon-o-archive-box class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('document-duplicate')
                                        <x-heroicon-o-document-duplicate class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('paper-airplane')
                                        <x-heroicon-o-paper-airplane class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('clipboard-document-list')
                                        <x-heroicon-o-clipboard-document-list class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('banknotes')
                                        <x-heroicon-o-banknotes class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('document-text')
                                        <x-heroicon-o-document-text class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('chart-bar')
                                        <x-heroicon-o-chart-bar class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('receipt-percent')
                                        <x-heroicon-o-receipt-percent class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('gift')
                                        <x-heroicon-o-gift class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('building-office')
                                        <x-heroicon-o-building-office class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('user-group')
                                        <x-heroicon-o-user-group class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('credit-card')
                                        <x-heroicon-o-credit-card class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('puzzle-piece')
                                        <x-heroicon-o-puzzle-piece class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('tag')
                                        <x-heroicon-o-tag class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('user')
                                        <x-heroicon-o-user class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('cloud-arrow-down')
                                        <x-heroicon-o-cloud-arrow-down class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('envelope')
                                        <x-heroicon-o-envelope class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('lock-closed')
                                        <x-heroicon-o-lock-closed class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                    @case('shield-check')
                                        <x-heroicon-o-shield-check class="h-5 w-5 {{ $iconColor }}" />
                                        @break
                                @endswitch
                                <span x-show="!collapsed" class="truncate font-medium">{{ $item['label'] }}</span>
                            </span>
                        </a>

                        @if(($item['label'] ?? null) === 'Finanzen' && $item['active'])
                            <div x-show="!collapsed" class="mt-2 ml-4 space-y-1 border-l border-slate-200 pl-4">
                                <div class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                    Was möchtest du erledigen?
                                </div>
                                @foreach($financeNav as $financeItem)
                                    <a href="{{ $financeItem['route'] }}"
                                       @if($financeItem['active']) aria-current="page" @endif
                                       class="flex items-start justify-between rounded-xl px-3 py-2.5 text-sm transition {{ $financeItem['active'] ? 'bg-slate-100 text-slate-950' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium {{ $financeItem['active'] ? 'font-semibold text-slate-950' : '' }}">{{ $financeItem['label'] }}</span>
                                            @if(!empty($financeItem['hint']))
                                                <span class="mt-0.5 block text-xs leading-5 {{ $financeItem['active'] ? 'text-slate-600' : 'text-slate-400' }}">
                                                    {{ $financeItem['hint'] }}
                                                </span>
                                            @endif
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if(($item['label'] ?? null) === 'Kalender' && $item['active'])
                            <div x-show="!collapsed" class="mt-2 ml-4 space-y-1 border-l border-slate-200 pl-4">
                                <div class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                    Was möchtest du tun?
                                </div>
                                @foreach($calendarNav as $calendarItem)
                                    <a href="{{ $calendarItem['route'] }}"
                                       @if($calendarItem['active']) aria-current="page" @endif
                                       class="flex items-start justify-between rounded-xl px-3 py-2.5 text-sm transition {{ $calendarItem['active'] ? 'bg-slate-100 text-slate-950' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium {{ $calendarItem['active'] ? 'font-semibold text-slate-950' : '' }}">{{ $calendarItem['label'] }}</span>
                                            @if(!empty($calendarItem['hint']))
                                                <span class="mt-0.5 block text-xs leading-5 {{ $calendarItem['active'] ? 'text-slate-600' : 'text-slate-400' }}">
                                                    {{ $calendarItem['hint'] }}
                                                </span>
                                            @endif
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <div class="border-t border-slate-200 pt-4">
        @if(!empty($personalNav))
            <section class="mb-3">
                <h2 x-show="!collapsed" class="px-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                    Persönlich
                </h2>
                <ul class="mt-2 space-y-1.5">
                    @foreach($personalNav as $item)
                        <li>
                            <a href="{{ $item['route'] }}"
                               @if($item['active']) aria-current="page" @endif
                               title="{{ $item['label'] }}"
                               :class="collapsed ? 'justify-center px-2' : 'justify-between px-3'"
                               class="group flex items-center gap-3 rounded-2xl py-2.5 transition-all duration-150 {{ $item['active'] ? 'bg-slate-950 text-white shadow-sm' : 'hover:bg-slate-100 text-slate-700' }}">
                                <span :class="collapsed ? 'justify-center' : ''" class="flex min-w-0 items-center gap-3">
                                    @php($iconColor = $item['active'] ? 'text-white' : 'text-slate-400 group-hover:text-slate-700')
                                    <x-heroicon-o-user class="h-5 w-5 {{ $iconColor }}" />
                                    <span x-show="!collapsed" class="truncate font-medium">{{ $item['label'] }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($user)
            <div x-show="!collapsed" class="mb-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                <div class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $user->roleLabel() }}</div>
            </div>
        @endif

        <button type="button"
                data-feedback-open
                :class="collapsed ? 'justify-center px-2' : 'justify-between px-3'"
                title="Feedback senden"
                class="group mb-2 flex w-full items-center rounded-2xl py-2.5 text-left text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
            <span :class="collapsed ? 'justify-center' : ''" class="flex items-center gap-3 font-medium">
                <span class="inline-flex h-5 w-5 items-center justify-center text-base">💬</span>
                <span x-show="!collapsed">Feedback senden</span>
            </span>
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                :class="collapsed ? 'justify-center px-2' : 'justify-between px-3'"
                    title="Abmelden"
                    class="group flex w-full items-center rounded-2xl py-2.5 text-left text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                <span :class="collapsed ? 'justify-center' : ''" class="flex items-center gap-3 font-medium">
                    <x-heroicon-o-arrow-left-on-rectangle class="h-5 w-5 text-slate-400 group-hover:text-slate-700" />
                    <span x-show="!collapsed">Abmelden</span>
                </span>
            </button>
        </form>
    </div>
</nav>
