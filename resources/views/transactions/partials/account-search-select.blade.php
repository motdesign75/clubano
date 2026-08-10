@php
    $fieldId = $id ?? $name;
    $selectedValue = (string) old($name, $selectedId ?? '');
    $isRequired = $required ?? true;
    $accountOptions = $accounts
        ->map(function ($account) {
            $label = trim(collect([
                $account->number,
                $account->name,
            ])->filter()->implode(' - '));

            $meta = collect([
                $account->type,
                $account->tax_area ? str_replace('_', ' ', $account->tax_area) : null,
                $account->chart_name,
                $account->tax_key,
                $account->datev_automatic ? 'DATEV-Automatik' : null,
                $account->iban,
            ])->filter()->implode(' · ');

            return [
                'id' => (string) $account->id,
                'label' => $label,
                'meta' => $meta,
                'search' => $label . ' ' . $meta,
            ];
        })
        ->values();
    $selectedOption = $accountOptions->firstWhere('id', $selectedValue);
@endphp

<div
    x-data="{
        open: false,
        query: '',
        selected: @js($selectedValue),
        options: @js($accountOptions),
        normalize(value) {
            return value.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        },
        get selectedOption() {
            return this.options.find((option) => option.id === this.selected) || null;
        },
        get filteredOptions() {
            const term = this.normalize(this.query.trim());

            if (term === '') {
                return this.options.slice(0, 18);
            }

            return this.options
                .filter((option) => this.normalize(option.search).includes(term))
                .slice(0, 30);
        },
        choose(option) {
            this.selected = option.id;
            this.query = '';
            this.open = false;
        },
        clear() {
            this.selected = '';
            this.query = '';
            this.open = true;
        }
    }"
    class="space-y-2"
    @keydown.escape.window="open = false"
>
    <label for="{{ $fieldId }}_search" class="{{ $labelClass ?? 'mb-1 block text-sm font-medium text-slate-600' }}">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" :value="selected" @if($isRequired) required @endif>
    @if($selectedOption)
        <span class="sr-only">{{ $selectedOption['label'] }} {{ $selectedOption['meta'] }}</span>
    @endif

    <div class="rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500">
        <div class="flex items-center gap-2 border-b border-slate-100 px-3 py-2">
            <input
                id="{{ $fieldId }}_search"
                type="search"
                x-model.debounce.120ms="query"
                @focus="open = true"
                @input="open = true"
                class="min-h-10 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
                placeholder="{{ $placeholder ?? 'Kontonummer oder Kontoname suchen' }}"
                autocomplete="off"
            >
            <button
                type="button"
                x-show="selected"
                x-cloak
                @click="clear()"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Auswahl entfernen"
            >
                &times;
            </button>
        </div>

        <button
            type="button"
            @click="open = !open"
            class="flex w-full items-start justify-between gap-3 px-3 py-3 text-left"
        >
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-slate-900" x-text="selectedOption ? selectedOption.label : 'Bitte Konto wählen'"></span>
                <span class="mt-0.5 block truncate text-xs text-slate-500" x-text="selectedOption ? selectedOption.meta : 'Suche nach Nummer, Name, Bereich oder DATEV-Merkmalen'"></span>
            </span>
            <span class="shrink-0 text-slate-400">▾</span>
        </button>
    </div>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="max-h-72 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-lg"
    >
        <template x-for="option in filteredOptions" :key="option.id">
            <button
                type="button"
                @click="choose(option)"
                class="flex w-full items-start justify-between gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-slate-50"
                :class="selected === option.id ? 'bg-blue-50 text-blue-900' : 'text-slate-700'"
            >
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold" x-text="option.label"></span>
                    <span class="mt-0.5 block truncate text-xs text-slate-500" x-text="option.meta"></span>
                </span>
                <span x-show="selected === option.id" class="shrink-0 text-xs font-semibold text-blue-700">Ausgewählt</span>
            </button>
        </template>

        <div x-show="filteredOptions.length === 0" class="px-3 py-6 text-center text-sm text-slate-500">
            Kein Konto gefunden.
        </div>
    </div>
</div>
