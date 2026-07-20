<div>
    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
    <input type="text" name="title" id="title" required
           value="{{ old('title', $event->title) }}"
           class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
</div>

<div>
    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
    <textarea name="description" id="description" rows="4"
              class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">{{ old('description', $event->description) }}</textarea>
    <p class="mt-2 text-sm text-gray-500">Du kannst hier Absätze, Listen, Links und Hervorhebungen formatieren.</p>
</div>

<div>
    <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
    <input type="text" name="location" id="location"
           value="{{ old('location', $event->location) }}"
           class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
</div>

<div>
    <label for="responsible_user_id" class="block text-sm font-medium text-gray-700 mb-1">Verantwortlich</label>
    <select name="responsible_user_id" id="responsible_user_id"
            class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
        <option value="">Niemand fest zugeordnet</option>
        @foreach(($users ?? collect()) as $user)
            <option value="{{ $user->id }}" @selected((string) old('responsible_user_id', $event->responsible_user_id) === (string) $user->id)>
                {{ $user->name }}
            </option>
        @endforeach
    </select>
    <p class="mt-2 text-sm text-gray-500">Optional. Hilft bei Rückfragen und macht Zuständigkeiten im Kalender sichtbar.</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-4 items-end">
    <div>
        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Kategorie</label>
        <select name="category_id" id="category_id"
                class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
            <option value="">Keine Kategorie</option>
            @foreach(($categories ?? collect()) as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $event->category_id) === (string) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>

    <a href="{{ route('event-categories.index') }}"
       class="inline-flex items-center justify-center rounded-xl border border-indigo-200 px-4 py-2.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
        Kategorien verwalten
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div>
        <label for="start" class="block text-sm font-medium text-gray-700 mb-1">Beginn</label>
        <input type="datetime-local" name="start" id="start" required
               value="{{ old('start', $event->start ? $event->start->format('Y-m-d\TH:i') : '') }}"
               class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
    </div>

    <div>
        <label for="end" class="block text-sm font-medium text-gray-700 mb-1">Ende</label>
        <input type="datetime-local" name="end" id="end" required
               value="{{ old('end', $event->end ? $event->end->format('Y-m-d\TH:i') : '') }}"
               class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
    </div>
</div>

<div>
    <label for="is_public" class="block text-sm font-medium text-gray-700 mb-1">Sichtbarkeit</label>
    <select name="is_public" id="is_public"
            class="w-full rounded-xl border-gray-300 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
        <option value="1" {{ old('is_public', $event->is_public) == 1 ? 'selected' : '' }}>Öffentlich</option>
        <option value="0" {{ old('is_public', $event->is_public) == 0 ? 'selected' : '' }}>Intern</option>
    </select>
</div>

<div class="grid gap-6 md:grid-cols-[1.2fr_0.8fr]">
    <div>
        <label for="image" class="block text-sm font-medium text-gray-700 mb-1">Veranstaltungsfoto</label>
        <input type="file" name="image" id="image" accept="image/*"
               class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm">
        <p class="mt-1 text-sm text-gray-500">Ideal fuer Eventseite, Übersicht und Social-Sharing.</p>

        @if($event->image_url)
            <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-3">
                <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="h-48 w-full rounded-xl object-cover">

                <label class="mt-3 inline-flex items-center text-sm text-gray-700">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300">
                    <span class="ml-2">Vorhandenes Foto entfernen</span>
                </label>
            </div>
        @endif
    </div>

    <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4">
        <label class="inline-flex items-start text-sm text-gray-800">
            <input type="checkbox" name="booking_enabled" value="1" class="mt-1 rounded border-gray-300" @checked(old('booking_enabled', $event->booking_enabled))>
            <span class="ml-3">
                <span class="block font-medium text-indigo-900">Veranstaltung buchbar machen</span>
                <span class="mt-1 block text-gray-600">Clubano erzeugt automatisch ein Anmeldeformular und verknüpft es mit der Eventseite.</span>
            </span>
        </label>

        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div>
                <label for="price_per_person" class="mb-1 block text-sm font-medium text-gray-700">Preis pro Person</label>
                <input type="number"
                       step="0.01"
                       min="0"
                       name="price_per_person"
                       id="price_per_person"
                       value="{{ old('price_per_person', number_format((float) ($event->price_per_person ?? 0), 2, '.', '')) }}"
                       class="w-full rounded-xl border-gray-300 bg-white focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">0,00 bedeutet kostenfreie Veranstaltung.</p>
            </div>

            <div>
                <label for="currency" class="mb-1 block text-sm font-medium text-gray-700">Währung</label>
                <input type="text"
                       name="currency"
                       id="currency"
                       maxlength="3"
                       value="{{ old('currency', strtoupper($event->currency ?: 'EUR')) }}"
                       class="w-full rounded-xl border-gray-300 bg-white uppercase focus:border-blue-500 focus:ring-blue-500 shadow-sm">
            </div>

            <div>
                <label for="max_participants_per_booking" class="mb-1 block text-sm font-medium text-gray-700">Max. Personen pro Anmeldung</label>
                <input type="number"
                       min="1"
                       max="50"
                       name="max_participants_per_booking"
                       id="max_participants_per_booking"
                       value="{{ old('max_participants_per_booking', max(1, (int) ($event->max_participants_per_booking ?: 1))) }}"
                       class="w-full rounded-xl border-gray-300 bg-white focus:border-blue-500 focus:ring-blue-500 shadow-sm">
            </div>
        </div>
    </div>
</div>
