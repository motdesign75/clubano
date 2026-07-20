<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight">Nummernkreise</h2>
    </x-slot>

    <div class="p-6 space-y-4">
        @if (session('success'))
            <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Deine Nummernkreise</h3>
            <a href="{{ route('number_ranges.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                ➕ Neuer Nummernkreis
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 shadow rounded">
                <thead class="bg-gray-100 text-left text-sm">
                    <tr>
                        <th class="px-4 py-2">Typ</th>
                        <th class="px-4 py-2">Format-Vorschau</th>
                        <th class="px-4 py-2">Aktueller Stand</th>
                        <th class="px-4 py-2">Jährlich zurücksetzen</th>
                        <th class="px-4 py-2">Aktion</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse ($ranges as $range)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium">{{ ucfirst($range->type) }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ $range->prefix }}{{ str_pad($range->current_number + 1, 4, '0', STR_PAD_LEFT) }}{{ $range->suffix }}
                            </td>
                            <td class="px-4 py-2">{{ $range->current_number }}</td>
                            <td class="px-4 py-2">
                                @if ($range->reset_yearly)
                                    <span class="text-green-700">✔️</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('number_ranges.edit', $range) }}"
                                   class="text-blue-600 hover:underline">Bearbeiten</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                Noch keine Nummernkreise definiert.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
