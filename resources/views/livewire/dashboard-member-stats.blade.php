@php
    $tabs = [
        'entries' => ['label' => 'Eintritte', 'count' => count($this->entries), 'tone' => 'emerald'],
        'exits' => ['label' => 'Austritte', 'count' => count($this->exits), 'tone' => 'rose'],
        'birthdays' => ['label' => 'Geburtstage', 'count' => count($this->birthdays), 'tone' => 'amber'],
        'anniversaries' => ['label' => 'Jubiläen', 'count' => count($this->anniversaries), 'tone' => 'indigo'],
    ];
@endphp

<div x-data="{ tab: 'entries' }" class="space-y-5">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-2">
        @foreach ($tabs as $key => $item)
            <button
                type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}'
                    ? 'bg-slate-950 text-white shadow-sm'
                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                class="flex min-h-12 items-center justify-between gap-3 rounded-xl px-4 py-2 text-left text-sm font-semibold transition">
                <span>{{ $item['label'] }}</span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700" :class="tab === '{{ $key }}' ? 'bg-white/15 text-white' : ''">{{ $item['count'] }}</span>
            </button>
        @endforeach
    </div>

    <div x-show="tab === 'entries'" class="space-y-3">
        @forelse ($this->entries as $member)
            <a href="{{ route('members.show', $member) }}"
               class="group grid gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 transition hover:border-emerald-200 hover:bg-emerald-50/40 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="min-w-0">
                    <div class="truncate text-base font-semibold text-slate-950">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Neu im Verein</div>
                </div>
                <div class="sm:text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->entry_date->format('d.m.Y') }}</div>
                    <div class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">Eintritt</div>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine Eintritte im aktuellen Jahr.
            </div>
        @endforelse
    </div>

    <div x-show="tab === 'exits'" class="space-y-3">
        @forelse ($this->exits as $member)
            <a href="{{ route('members.show', $member) }}"
               class="group grid gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 transition hover:border-rose-200 hover:bg-rose-50/40 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="min-w-0">
                    <div class="truncate text-base font-semibold text-slate-950">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Verlässt den Verein</div>
                </div>
                <div class="sm:text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->exit_date->format('d.m.Y') }}</div>
                    <div class="mt-1 inline-flex rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-100">Austritt</div>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine Austritte im aktuellen Jahr.
            </div>
        @endforelse
    </div>

    <div x-show="tab === 'birthdays'" class="space-y-3">
        @forelse ($this->birthdays as $member)
            <a href="{{ route('members.show', $member) }}"
               class="group grid gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 transition hover:border-amber-200 hover:bg-amber-50/40 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="min-w-0">
                    <div class="truncate text-base font-semibold text-slate-950">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Geburtstag am {{ $member->next_birthday_date->translatedFormat('d. F') }}</div>
                </div>
                <div class="sm:text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->next_birthday_date->format('d.m.Y') }}</div>
                    <div class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-100">wird {{ $member->next_birthday_age }}</div>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine anstehenden Geburtstage mehr in diesem Jahr.
            </div>
        @endforelse
    </div>

    <div x-show="tab === 'anniversaries'" class="space-y-3">
        @forelse ($this->anniversaries as $member)
            <a href="{{ route('members.show', $member) }}"
               class="group grid gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 transition hover:border-indigo-200 hover:bg-indigo-50/40 sm:grid-cols-[1fr_auto] sm:items-center">
                <div class="min-w-0">
                    <div class="truncate text-base font-semibold text-slate-950">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Mitglied seit {{ $member->entry_date->format('Y') }}</div>
                </div>
                <div class="sm:text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->anniversary_date->format('d.m.Y') }}</div>
                    <div class="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100">{{ $member->anniversary_years }} Jahre</div>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine anstehenden Jubiläen mehr in diesem Jahr.
            </div>
        @endforelse
    </div>
</div>
