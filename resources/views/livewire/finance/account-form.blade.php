<div class="max-w-2xl space-y-6">
    <h2 class="text-2xl font-bold text-gray-800">Konto anlegen</h2>

    <form wire:submit.prevent="save" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Kontoname</label>
            <input type="text" wire:model="name" class="w-full rounded border-gray-300" required>
            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Typ</label>
            <select wire:model="type" class="w-full rounded border-gray-300">
                <option value="kasse">Barkasse</option>
                <option value="bank">Bankkonto</option>
            </select>
            @error('type') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">IBAN</label>
            <input type="text" wire:model="iban" class="w-full rounded border-gray-300">
            @error('iban') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">BIC</label>
            <input type="text" wire:model="bic" class="w-full rounded border-gray-300">
            @error('bic') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
            <textarea wire:model="description" rows="3" class="w-full rounded border-gray-300"></textarea>
        </div>

        <div class="flex items-center space-x-2">
            <input type="checkbox" wire:model="active" id="active" class="rounded border-gray-300">
            <label for="active" class="text-sm text-gray-700">Aktiv</label>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Speichern
        </button>
    </form>
</div>
