<div x-data="{ tab: 'entries' }" class="space-y-5">
    <div class="flex flex-wrap gap-2">
        @foreach (['entries' => 'Eintritte', 'exits' => 'Austritte', 'birthdays' => 'Geburtstage', 'anniversaries' => 'Jubiläen'] as $key => $label)
            <button
                type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}'
                    ? 'bg-slate-950 text-white shadow-sm'
                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                class="rounded-full px-4 py-2 text-sm font-semibold transition">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div x-show="tab === 'entries'" class="space-y-3">
        @forelse ($this->entries as $member)
            <a href="{{ route('members.show', $member) }}"
               class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 transition hover:bg-slate-50">
                <div>
                    <div class="text-base font-semibold text-slate-900">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Neu im Verein</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->entry_date->format('d.m.Y') }}</div>
                    <div class="mt-1 text-xs text-emerald-700">Eintritt</div>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine Eintritte im aktuellen Jahr.
            </div>
        @endforelse
    </div>

    <div x-show="tab === 'exits'" class="space-y-3">
        @forelse ($this->exits as $member)
            <a href="{{ route('members.show', $member) }}"
               class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 transition hover:bg-slate-50">
                <div>
                    <div class="text-base font-semibold text-slate-900">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Verlässt den Verein</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->exit_date->format('d.m.Y') }}</div>
                    <div class="mt-1 text-xs text-rose-700">Austritt</div>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine Austritte im aktuellen Jahr.
            </div>
        @endforelse
    </div>

    <div x-show="tab === 'birthdays'" class="space-y-3">
        @forelse ($this->birthdays as $member)
            <a href="{{ route('members.show', $member) }}"
               class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 transition hover:bg-slate-50">
                <div>
                    <div class="text-base font-semibold text-slate-900">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Geburtstag steht an</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->next_birthday_date->format('d.m.Y') }}</div>
                    <div class="mt-1 text-xs text-amber-700">wird {{ $member->next_birthday_age }}</div>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine anstehenden Geburtstage mehr in diesem Jahr.
            </div>
        @endforelse
    </div>

    <div x-show="tab === 'anniversaries'" class="space-y-3">
        @forelse ($this->anniversaries as $member)
            <a href="{{ route('members.show', $member) }}"
               class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 transition hover:bg-slate-50">
                <div>
                    <div class="text-base font-semibold text-slate-900">{{ $member->full_name }}</div>
                    <div class="mt-1 text-sm text-slate-500">Mitglied seit {{ $member->entry_date->format('Y') }}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-slate-900">{{ $member->anniversary_date->format('d.m.Y') }}</div>
                    <div class="mt-1 text-xs text-indigo-700">{{ $member->anniversary_years }} Jahre</div>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Keine anstehenden Jubiläen mehr in diesem Jahr.
            </div>
        @endforelse
    </div>
</div>
