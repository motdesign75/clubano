<div class="space-y-4">

    {{-- Titel --}}
    <div>
        <label for="title" class="block text-sm font-medium text-gray-700">Titel</label>
        <input type="text" name="title" id="title"
               value="{{ old('title', $event->title ?? '') }}"
               required
               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
    </div>

    {{-- Beschreibung --}}
    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Beschreibung</label>
        <textarea name="description" id="description" rows="4"
                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $event->description ?? '') }}</textarea>
    </div>

    {{-- Ort --}}
    <div>
        <label for="location" class="block text-sm font-medium text-gray-700">Ort</label>
        <input type="text" name="location" id="location"
               value="{{ old('location', $event->location ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
    </div>

    {{-- Startzeit --}}
    <div>
        <label for="start" class="block text-sm font-medium text-gray-700">Beginn</label>
        <input type="datetime-local" name="start" id="start"
               value="{{ old('start', isset($event->start) ? $event->start->format('Y-m-d\TH:i') : '') }}"
               required
               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
    </div>

    {{-- Endzeit --}}
    <div>
        <label for="end" class="block text-sm font-medium text-gray-700">Ende</label>
        <input type="datetime-local" name="end" id="end"
               value="{{ old('end', isset($event->end) ? $event->end->format('Y-m-d\TH:i') : '') }}"
               required
               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
    </div>

    {{-- Öffentlich / Intern --}}
    <div>
        <label for="is_public" class="block text-sm font-medium text-gray-700">Sichtbarkeit</label>
        <select name="is_public" id="is_public"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <option value="1" {{ old('is_public', $event->is_public ?? '') == 1 ? 'selected' : '' }}>Öffentlich</option>
            <option value="0" {{ old('is_public', $event->is_public ?? '') == 0 ? 'selected' : '' }}>Intern</option>
        </select>
    </div>

</div>
