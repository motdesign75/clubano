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
            'hint' => 'Mitgliederliste und Profile',
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
                || request()->routeIs('bank-imports.*')
                || request()->routeIs('donations.*')
                || request()->routeIs('invoices.*')
                || request()->routeIs('vouchers.*')
                || request()->routeIs('payments.*'),
            'icon' => 'banknotes',
            'minRole' => 'finance',
        ],
    ];

    $workNav = [
        [
            'label' => 'Formulare',
            'hint' => 'Antworten sammeln und übernehmen',
            'route' => route('forms.index'),
            'active' => request()->routeIs('forms.*'),
            'icon' => 'link',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Kontakte & Firmen',
            'hint' => 'Externe Personen, Firmen und Partner',
            'route' => route('contacts.index'),
            'active' => request()->routeIs('contacts.*'),
            'icon' => 'identification',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Projekte',
            'hint' => 'Größere Vorhaben bündeln',
            'route' => route('projects.index'),
            'active' => request()->routeIs('projects.*'),
            'icon' => 'rectangle-stack',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Aufgaben',
            'hint' => 'To-dos, Verantwortung und Fristen',
            'route' => route('tasks.index'),
            'active' => request()->routeIs('tasks.*'),
            'icon' => 'check-circle',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Dokumente',
            'hint' => 'Dateien zentral ablegen und finden',
            'route' => route('documents.index'),
            'active' => request()->routeIs('documents.*'),
            'icon' => 'archive-box',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Protokolle',
            'hint' => 'Sitzungen, Beschlüsse und Mitschriften',
            'route' => route('protocols.index'),
            'active' => request()->routeIs('protocols.*'),
            'icon' => 'document-duplicate',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'E-Mail schreiben',
            'hint' => 'Direkte Nachricht mit Anhängen senden',
            'route' => route('mail.create'),
            'active' => request()->routeIs('mail.*'),
            'icon' => 'paper-airplane',
            'minRole' => 'Mitarbeiter',
        ],
        [
            'label' => 'Vorlagen',
            'hint' => 'Wiederverwendbare Texte gestalten',
            'route' => route('templates.index'),
            'active' => request()->routeIs('templates.*') || request()->routeIs('letters.*'),
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
            'minRole' => 'finance',
        ],
        [
            'label' => 'Kassenbuch',
            'hint' => 'Barbewegungen erfassen und prüfen',
            'route' => route('transactions.cashbook'),
            'active' => request()->routeIs('transactions.cashbook'),
            'icon' => 'banknotes',
            'minRole' => 'finance',
        ],
        [
            'label' => 'Geldbewegungen',
            'hint' => 'Alles sehen, was rein- oder rausgeht',
            'route' => route('transactions.index'),
            'active' => request()->routeIs('transactions.index') || request()->routeIs('transactions.create') || request()->routeIs('transactions.edit') || request()->routeIs('transactions.cancel*'),
            'icon' => 'document-text',
            'minRole' => 'finance',
        ],
        [
            'label' => 'Bankumsätze',
            'hint' => 'Kontoauszüge importieren und sicher verbuchen',
            'route' => route('bank-imports.index'),
            'active' => request()->routeIs('bank-imports.*'),
            'icon' => 'arrow-down-tray',
            'minRole' => 'finance',
        ],
        [
            'label' => 'Auswertungen',
            'hint' => 'EÜR, Journal und Jahresabschluss vorbereiten',
            'route' => route('transactions.corporation-tax'),
            'active' => request()->routeIs('transactions.summary') || request()->routeIs('transactions.eur') || request()->routeIs('transactions.journal*') || request()->routeIs('transactions.corporation-tax'),
            'icon' => 'chart-bar',
            'minRole' => 'finance',
        ],
        [
            'label' => 'Spenden',
            'hint' => 'Spenden erfassen und Bestätigungen erstellen',
            'route' => route('donations.index'),
            'active' => request()->routeIs('donations.*'),
            'icon' => 'gift',
            'minRole' => 'finance',
        ],
        [
            'label' => 'Gutscheine',
            'hint' => 'Gutscheine anlegen und Einlösungen verfolgen',
            'route' => route('vouchers.index'),
            'active' => request()->routeIs('vouchers.*'),
            'icon' => 'gift',
            'minRole' => 'finance',
        ],
        [
            'label' => 'Rechnungen',
            'hint' => 'Rechnungen schreiben und Zahlungen verfolgen',
            'route' => route('invoices.index'),
            'active' => request()->routeIs('invoices.*'),
            'icon' => 'receipt-percent',
            'minRole' => 'finance',
        ],
        [
            'label' => 'Haushaltsplan',
            'hint' => 'Planen, vergleichen und dem Vorstand zeigen',
            'route' => route('budgets.index'),
            'active' => request()->routeIs('budgets.*'),
            'icon' => 'presentation-chart-line',
            'minRole' => 'finance',
        ],
    ];

    $calendarNav = [
        [
            'label' => 'Kalender',
            'hint' => 'Termine sehen und den Monat planen',
            'route' => route('events.index'),
            'active' => request()->routeIs('events.index'),
            'icon' => 'calendar',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Termin anlegen',
            'hint' => 'Training, Spiel, Sitzung oder Einsatz anlegen',
            'route' => route('events.create'),
            'active' => request()->routeIs('events.create'),
            'icon' => 'plus',
            'minRole' => 'events',
        ],
        [
            'label' => 'Terminarten',
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
            'minRole' => 'events',
        ],
        [
            'label' => 'Aushang',
            'hint' => 'Termine für Vereinsheim oder Infofläche drucken',
            'route' => route('events.poster'),
            'active' => request()->routeIs('events.poster') || request()->routeIs('events.poster.print') || request()->routeIs('events.poster.pdf'),
            'icon' => 'printer',
            'minRole' => 'events',
        ],
    ];

    $organizationNav = [
        [
            'label' => 'Verein',
            'hint' => 'Adresse, Logo und Stammdaten',
            'route' => route('tenant.show'),
            'active' => request()->routeIs('tenant.*'),
            'icon' => 'building-office',
            'minRole' => 'Lesen',
        ],
        [
            'label' => 'Benutzer',
            'hint' => 'Menschen zur Mitarbeit einladen',
            'route' => route('users.index'),
            'active' => request()->routeIs('users.*'),
            'icon' => 'user-group',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Datenschutz',
            'hint' => 'Auskunft, Löschung und Nachweise',
            'route' => route('privacy.index'),
            'active' => request()->routeIs('privacy.*'),
            'icon' => 'shield-check',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Beiträge & Arten',
            'hint' => 'Beitragsmodelle und Mitgliedsarten',
            'route' => route('memberships.index'),
            'active' => request()->routeIs('memberships.*'),
            'icon' => 'credit-card',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Felder anpassen',
            'hint' => 'Eigene Angaben für den Verein',
            'route' => route('custom-fields.index'),
            'active' => request()->routeIs('custom-fields.*'),
            'icon' => 'puzzle-piece',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Markierungen',
            'hint' => 'Gruppen und Kennzeichen vergeben',
            'route' => route('tags.index'),
            'active' => request()->routeIs('tags.*'),
            'icon' => 'tag',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'Import',
            'hint' => 'Daten aus Excel, CSV oder DATEV übernehmen',
            'route' => route('import.index'),
            'active' => request()->routeIs('import.*'),
            'icon' => 'cloud-arrow-down',
            'minRole' => 'Admin',
        ],
        [
            'label' => 'E-Mail-Absender',
            'hint' => 'SMTP und Absender einstellen',
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
            'label' => 'Mitteilungen',
            'route' => route('admin.announcements.index'),
            'active' => request()->routeIs('admin.announcements.*'),
            'icon' => 'megaphone',
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

    $calendarNav = collect($calendarNav)
        ->filter(fn ($item) => ($user?->hasPermission($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();

    $financeNav = collect($financeNav)
        ->filter(fn ($item) => ($user?->hasPermission($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();

    $workNav = collect($workNav)
        ->filter(fn ($item) => ($user?->hasPermission($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();

    $organizationNav = collect($organizationNav)
        ->filter(fn ($item) => ($user?->hasPermission($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();

    $dashboardNav = collect($primaryNav)
        ->filter(fn ($item) => ($item['label'] ?? null) === 'Dashboard')
        ->filter(fn ($item) => ($user?->hasPermission($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();

    $moduleGroups = [];

    if (! ($user?->isSuperAdmin() && blank($user->tenant_id))) {
        $moduleGroups = [
            [
                'label' => 'Menschen',
                'hint' => 'Mitglieder, Kontakte, Firmen',
                'icon' => 'users',
                'route' => route('members.index'),
                'active' => request()->routeIs('members.*') || request()->routeIs('contacts.*'),
                'children' => collect([
                    collect($primaryNav)->firstWhere('label', 'Mitglieder'),
                    collect($workNav)->firstWhere('label', 'Kontakte & Firmen'),
                ])->filter()->values()->all(),
            ],
            [
                'label' => 'Termine & Teilnahme',
                'hint' => 'Kalender, Anmeldung, Anwesenheit',
                'icon' => 'calendar',
                'route' => route('events.index'),
                'active' => request()->routeIs('events.*') || request()->routeIs('event-categories.*'),
                'children' => $calendarNav,
            ],
            [
                'label' => 'Geld & Rechnungen',
                'hint' => 'Konten, Buchungen, Spenden',
                'icon' => 'banknotes',
                'route' => route('transactions.index'),
                'active' => request()->routeIs('accounts.*')
                    || request()->routeIs('transactions.*')
                    || request()->routeIs('donations.*')
                    || request()->routeIs('invoices.*')
                    || request()->routeIs('vouchers.*')
                    || request()->routeIs('payments.*')
                    || request()->routeIs('budgets.*'),
                'children' => $financeNav,
            ],
            [
                'label' => 'Nachrichten & Vorlagen',
                'hint' => 'Formulare, Protokolle, E-Mails',
                'icon' => 'paper-airplane',
                'route' => route('templates.index'),
                'active' => request()->routeIs('forms.*')
                    || request()->routeIs('protocols.*')
                    || request()->routeIs('templates.*')
                    || request()->routeIs('mail.*')
                    || request()->routeIs('letters.*'),
                'children' => collect([
                    collect($workNav)->firstWhere('label', 'Formulare'),
                    collect($workNav)->firstWhere('label', 'Protokolle'),
                    collect($workNav)->firstWhere('label', 'E-Mail schreiben'),
                    collect($workNav)->firstWhere('label', 'Vorlagen'),
                ])->filter()->values()->all(),
            ],
            [
                'label' => 'Aufgaben & Ablage',
                'hint' => 'Aufgaben, Projekte, Dokumente',
                'icon' => 'rectangle-stack',
                'route' => route('tasks.index'),
                'active' => request()->routeIs('projects.*')
                    || request()->routeIs('tasks.*')
                    || request()->routeIs('documents.*'),
                'children' => collect([
                    collect($workNav)->firstWhere('label', 'Aufgaben'),
                    collect($workNav)->firstWhere('label', 'Projekte'),
                    collect($workNav)->firstWhere('label', 'Dokumente'),
                ])->filter()->values()->all(),
            ],
            [
                'label' => 'Verein verwalten',
                'hint' => 'Benutzer, Rollen, Import, Datenschutz',
                'icon' => 'building-office',
                'route' => route('tenant.show'),
                'active' => request()->routeIs('tenant.*')
                    || request()->routeIs('users.*')
                    || request()->routeIs('privacy.*')
                    || request()->routeIs('memberships.*')
                    || request()->routeIs('custom-fields.*')
                    || request()->routeIs('tags.*')
                    || request()->routeIs('import.*')
                    || request()->routeIs('roles.*')
                    || request()->is('settings/email'),
                'children' => $organizationNav,
            ],
        ];
    }

    $moduleGroups = collect($moduleGroups)
        ->map(function ($group) {
            $group['children'] = collect($group['children'] ?? [])->filter()->values()->all();
            $group['route'] = $group['children'][0]['route'] ?? $group['route'];

            return $group;
        })
        ->filter(fn ($group) => !empty($group['children']))
        ->values()
        ->all();

    $systemNav = collect($systemNav)->values()->all();

    $personalNav = collect($personalNav)
        ->filter(fn ($item) => ($user?->hasPermission($item['minRole'] ?? 'Lesen')) ?? false)
        ->values()
        ->all();

    $iconComponent = fn (string $icon) => 'heroicon-o-' . match ($icon) {
        'home' => 'home',
        'users' => 'users',
        'calendar' => 'calendar',
        'plus' => 'plus',
        'swatch' => 'swatch',
        'printer' => 'printer',
        'link' => 'link',
        'identification' => 'identification',
        'rectangle-stack' => 'rectangle-stack',
        'check-circle' => 'check-circle',
        'archive-box' => 'archive-box',
        'document-duplicate' => 'document-duplicate',
        'paper-airplane' => 'paper-airplane',
        'clipboard-document-list' => 'clipboard-document-list',
        'banknotes' => 'banknotes',
        'document-text' => 'document-text',
        'chart-bar' => 'chart-bar',
        'receipt-percent' => 'receipt-percent',
        'gift' => 'gift',
        'building-office' => 'building-office',
        'building-office-2' => 'building-office-2',
        'user-group' => 'user-group',
        'credit-card' => 'credit-card',
        'puzzle-piece' => 'puzzle-piece',
        'tag' => 'tag',
        'user' => 'user',
        'cloud-arrow-down' => 'cloud-arrow-down',
        'envelope' => 'envelope',
        'lock-closed' => 'lock-closed',
        'shield-check' => 'shield-check',
        'megaphone' => 'megaphone',
        'presentation-chart-line' => 'presentation-chart-line',
        default => 'circle-stack',
    };
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

<nav :class="collapsed ? 'space-y-4 px-2 py-4' : 'space-y-5 px-4 py-5'" class="text-sm text-slate-700 transition-all duration-200" aria-label="Hauptnavigation">
    @if(!empty($dashboardNav))
        <section>
            <h2 x-show="!collapsed" class="px-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                Start
            </h2>
            <ul class="mt-2 space-y-1.5">
                @foreach($dashboardNav as $item)
                    @php($iconColor = $item['active'] ? 'text-white' : 'text-slate-400 group-hover:text-slate-700')
                    <li>
                        <a href="{{ $item['route'] }}"
                           @if($item['active']) aria-current="page" @endif
                           title="{{ $item['label'] }}"
                           :class="collapsed ? 'justify-center px-2' : 'justify-between px-3'"
                           class="group flex items-center gap-3 rounded-2xl py-2.5 transition-all duration-150 {{ $item['active'] ? 'bg-slate-950 text-white shadow-sm' : 'hover:bg-slate-100 text-slate-700' }}">
                            <span :class="collapsed ? 'justify-center' : ''" class="flex min-w-0 items-center gap-3">
                                <x-dynamic-component :component="$iconComponent($item['icon'])" class="h-5 w-5 {{ $iconColor }}" />
                                <span x-show="!collapsed" class="truncate font-medium">{{ $item['label'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if(!empty($moduleGroups))
        <section x-show="!collapsed" class="space-y-2">
            <h2 class="px-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                Bereiche
            </h2>

            @foreach($moduleGroups as $group)
                <div x-data="{ open: {{ $group['active'] ? 'true' : 'false' }} }" class="rounded-2xl border {{ $group['active'] ? 'border-slate-300 bg-slate-50' : 'border-transparent' }}">
                    <button type="button"
                            @click="open = !open"
                            class="group flex w-full items-center justify-between gap-3 rounded-2xl px-3 py-2.5 text-left transition {{ $group['active'] ? 'text-slate-950' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span class="flex min-w-0 items-center gap-3">
                            <x-dynamic-component :component="$iconComponent($group['icon'])" class="h-5 w-5 {{ $group['active'] ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-700' }}" />
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $group['label'] }}</span>
                                @if(!empty($group['hint']))
                                    <span class="mt-0.5 block truncate text-xs font-normal {{ $group['active'] ? 'text-slate-500' : 'text-slate-400' }}">
                                        {{ $group['hint'] }}
                                    </span>
                                @endif
                            </span>
                        </span>
                        <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-slate-400 transition" x-bind:class="open ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="open" class="pb-2">
                        <div class="ml-5 space-y-1 border-l border-slate-200 pl-3">
                            @foreach($group['children'] as $child)
                                <a href="{{ $child['route'] }}"
                                   @if($child['active']) aria-current="page" @endif
                                   class="flex items-start gap-2 rounded-xl px-3 py-2 text-sm transition {{ $child['active'] ? 'bg-white text-slate-950 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:bg-white hover:text-slate-900' }}">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $child['active'] ? 'bg-slate-950' : 'bg-slate-300' }}"></span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium">{{ $child['label'] }}</span>
                                        @if(!empty($child['hint']))
                                            <span class="mt-0.5 block text-xs leading-5 {{ $child['active'] ? 'text-slate-600' : 'text-slate-400' }}">
                                                {{ $child['hint'] }}
                                            </span>
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <section x-show="collapsed">
            <ul class="space-y-1.5">
                @foreach($moduleGroups as $group)
                    <li>
                        <a href="{{ $group['route'] }}"
                           title="{{ $group['label'] }}"
                           class="group flex justify-center rounded-2xl px-2 py-2.5 transition {{ $group['active'] ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700' }}">
                            <x-dynamic-component :component="$iconComponent($group['icon'])" class="h-5 w-5" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if(!empty($systemNav))
        <section>
            <h2 x-show="!collapsed" class="px-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                System
            </h2>
            <ul class="mt-2 space-y-1.5">
                @foreach($systemNav as $item)
                    @php($iconColor = $item['active'] ? 'text-white' : 'text-slate-400 group-hover:text-slate-700')
                    <li>
                        <a href="{{ $item['route'] }}"
                           @if($item['active']) aria-current="page" @endif
                           title="{{ $item['label'] }}"
                           :class="collapsed ? 'justify-center px-2' : 'justify-between px-3'"
                           class="group flex items-center gap-3 rounded-2xl py-2.5 transition-all duration-150 {{ $item['active'] ? 'bg-slate-950 text-white shadow-sm' : 'hover:bg-slate-100 text-slate-700' }}">
                            <span :class="collapsed ? 'justify-center' : ''" class="flex min-w-0 items-center gap-3">
                                <x-dynamic-component :component="$iconComponent($item['icon'])" class="h-5 w-5 {{ $iconColor }}" />
                                <span x-show="!collapsed" class="truncate font-medium">{{ $item['label'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

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
