@extends('layouts.sidebar')

@section('title', 'Mitgliedschaften verwalten')

@section('content')
    <div class="space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">⚙️ Mitgliedschaften</h1>
            <a href="{{ route('memberships.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                ➕ Neue Mitgliedschaft
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-800 px-4 py-2 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if($memberships->isEmpty())
            <p class="text-gray-500">Keine Mitgliedschaften vorhanden.</p>
        @else
            <table class="w-full table-auto bg-white shadow rounded overflow-hidden text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="px-4 py-2">Bezeichnung</th>
                        <th class="px-4 py-2">Beitrag (€)</th>
                        <th class="px-4 py-2">Abrechnung</th>
                        <th class="px-4 py-2 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($memberships as $membership)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $membership->name }}</td>
                            <td class="px-4 py-2">
                                {{ number_format($membership->fee ?? 0, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-2">
                                @php
                                    $labels = [
                                        'monatlich' => 'monatlich',
                                        'quartalsweise' => 'vierteljährlich',
                                        'halbjährlich' => 'halbjährlich',
                                        'jährlich' => 'jährlich',
                                    ];
                                @endphp
                                {{ $labels[$membership->billing_cycle] ?? ucfirst($membership->billing_cycle) }}
                            </td>
                            <td class="px-4 py-2 text-right space-x-2">
                                <a href="{{ route('memberships.edit', $membership) }}" class="text-yellow-600 hover:underline">Bearbeiten</a>
                                <form action="{{ route('memberships.destroy', $membership) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('Wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
