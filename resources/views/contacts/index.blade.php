@extends('layouts.app')

@section('title', 'Kontakte')

@section('content')
    @php
        $activeContactsCount = $contacts->getCollection()->where('is_active', true)->count();
        $favoriteContactsCount = $contacts->getCollection()->where('is_favorite', true)->count();
        $contactIdQuery = $contacts->getCollection()->pluck('id')->implode(',');
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Kontakte</div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Menschen, Firmen und Ansprechpartner klar beisammen</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                        Schnell finden, sauber pflegen und ohne Reibung in die passende Kommunikation wechseln.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-200">
                        <div class="font-semibold text-white">{{ $contacts->total() }} Kontakte</div>
                        <div class="mt-0.5 text-xs text-slate-300">{{ $activeContactsCount }} aktiv, {{ $favoriteContactsCount }} Favoriten</div>
                    </div>

                    @can('create', App\Models\Contact::class)
                        <a href="{{ route('contacts.create') }}"
                           class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-100">
                            Neuer Kontakt
                        </a>
                    @endcan
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET"
              action="{{ route('contacts.index') }}"
              class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Kontakte suchen und filtern
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $contacts->total() }} Kontakt{{ $contacts->total() === 1 ? '' : 'e' }} gefunden
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-700">
                        <input type="checkbox"
                               name="favorites"
                               value="1"
                               @checked($favorites)
                               class="rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                        Favoriten
                    </label>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto]">

                <div>
                    <label for="q" class="block text-sm font-medium text-slate-700">
                        Suche
                    </label>
                    <input type="text"
                           name="q"
                           id="q"
                           value="{{ $q }}"
                           placeholder="Name, Firma, E-Mail, Ort ..."
                           class="mt-1 block w-full rounded-2xl border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>

                <div>
                    <label for="contact_type" class="block text-sm font-medium text-slate-700">
                        Typ
                    </label>
                    <select name="contact_type"
                            id="contact_type"
                            class="mt-1 block w-full rounded-2xl border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Alle</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected($contactType === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category" class="block text-sm font-medium text-slate-700">
                        Kategorie
                    </label>
                    <select name="category"
                            id="category"
                            class="mt-1 block w-full rounded-2xl border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Alle</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" @selected($category === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">
                        Status
                    </label>
                    <select name="status"
                            id="status"
                            class="mt-1 block w-full rounded-2xl border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                        <option value="">Alle</option>
                        <option value="active" @selected($status === 'active')>Aktiv</option>
                        <option value="inactive" @selected($status === 'inactive')>Inaktiv</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-full bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Filtern
                    </button>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('contacts.index') }}"
                   class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                    Zurücksetzen
                </a>
            </div>
        </form>

        @if($contactIdQuery !== '')
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Serienaktionen fuer die aktuelle Auswahl</div>
                        <div class="mt-1 text-sm text-slate-500">{{ $contacts->count() }} Kontakt(e) auf dieser Seite koennen direkt in die Kommunikation uebernommen werden.</div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('mail.create', ['contacts' => $contactIdQuery]) }}"
                           class="inline-flex rounded-full bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                            Serienmail
                        </a>
                        <a href="{{ route('letters.create', ['contacts' => $contactIdQuery]) }}"
                           class="inline-flex rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Serienbrief
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Kontakt
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Kategorie
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Kommunikation
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Ort
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Status
                            </th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Aktion
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($contacts as $contact)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">
                                            {{ mb_substr($contact->display_name, 0, 1) }}
                                        </div>

                                        <div>
                                            <div class="font-semibold text-slate-900">
                                                @if($contact->is_favorite)
                                                    <span title="Favorit">★</span>
                                                @endif

                                                <a href="{{ route('contacts.show', $contact) }}"
                                                   class="hover:text-indigo-700">
                                                    {{ $contact->display_name }}
                                                </a>
                                            </div>

                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ $types[$contact->contact_type] ?? ucfirst($contact->contact_type ?? 'Kontakt') }}

                                                @if($contact->position)
                                                    · {{ $contact->position }}
                                                @endif

                                                @if($contact->department)
                                                    · {{ $contact->department }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top text-sm text-slate-700">
                                    @if($contact->category)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                            {{ $categories[$contact->category] ?? ucfirst($contact->category) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-4 align-top text-sm text-slate-700">
                                    <div class="space-y-1">
                                        @if($contact->primary_email)
                                            <div>
                                                <a href="mailto:{{ $contact->primary_email }}"
                                                   class="text-indigo-600 hover:text-indigo-800">
                                                    {{ $contact->primary_email }}
                                                </a>
                                            </div>
                                        @endif

                                        @if($contact->primary_phone)
                                            <div>
                                                <a href="tel:{{ $contact->primary_phone }}"
                                                   class="text-slate-700 hover:text-indigo-700">
                                                    {{ $contact->primary_phone }}
                                                </a>
                                            </div>
                                        @endif

                                        @if(!$contact->primary_email && !$contact->primary_phone)
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-top text-sm text-slate-700">
                                    @if($contact->city || $contact->zip)
                                        {{ trim(($contact->zip ?? '') . ' ' . ($contact->city ?? '')) }}
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium
                                        {{ $contact->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $contact->status_label }}
                                    </span>
                                </td>

                                <td class="px-4 py-4 align-top text-right text-sm">
                                    <div class="flex justify-end gap-3">
                                        @can('view', $contact)
                                            <a href="{{ route('contacts.show', $contact) }}"
                                               class="font-medium text-indigo-600 hover:text-indigo-900">
                                                Anzeigen
                                            </a>
                                        @endcan

                                        @if($contact->primary_email)
                                            <a href="{{ route('mail.create', ['contacts' => $contact->id]) }}"
                                               class="text-slate-600 hover:text-slate-900">
                                                Mail
                                            </a>
                                        @endif

                                        @can('update', $contact)
                                            <a href="{{ route('contacts.edit', $contact) }}"
                                               class="text-slate-600 hover:text-slate-900">
                                                Bearbeiten
                                            </a>
                                        @endcan

                                        @can('delete', $contact)
                                            <form method="POST"
                                                  action="{{ route('contacts.destroy', $contact) }}"
                                                  onsubmit="return confirm('Kontakt wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                        class="text-slate-500 hover:text-slate-900">
                                                    Löschen
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="mx-auto max-w-md">
                                        <h3 class="text-base font-semibold text-slate-900">
                                            Keine Kontakte gefunden
                                        </h3>

                                        <p class="mt-2 text-sm text-slate-500">
                                            Lege deinen ersten Kontakt an oder passe die Filter an.
                                        </p>

                                        <div class="mt-5 flex flex-col justify-center gap-2 sm:flex-row">
                                            <a href="{{ route('contacts.index') }}"
                                               class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                                                Filter zurücksetzen
                                            </a>

                                            @can('create', App\Models\Contact::class)
                                                <a href="{{ route('contacts.create') }}"
                                                   class="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                                                    Kontakt erstellen
                                                </a>
                                            @endcan
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($contacts->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $contacts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
