@extends('layouts.app')

@section('title', 'Mitgliedschaft bearbeiten')

@section('content')
    <div class="max-w-xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">✏️ Mitgliedschaft bearbeiten</h1>

        <form method="POST" action="{{ route('memberships.update', $membership) }}">
            @csrf
            @method('PATCH')

            <div class="space-y-4 bg-white p-6 rounded shadow">
                <div>
                    <label class="block font-medium text-sm text-gray-700">Bezeichnung</label>
                    <input type="text" name="name" value="{{ old('name', $membership->name) }}" required class="w-full mt-1 border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Beitrag (€)</label>
                    <input type="number" name="fee" step="0.01" min="0" value="{{ old('fee', $membership->fee) }}" class="w-full mt-1 border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Abrechnung</label>
                    <select name="billing_cycle" required class="w-full mt-1 border rounded px-3 py-2">
                        @php
                            $cycles = ['monatlich' => 'monatlich', 'quartalsweise' => 'vierteljährlich', 'halbjährlich' => 'halbjährlich', 'jährlich' => 'jährlich'];
                        @endphp
                        @foreach($cycles as $key => $label)
                            <option value="{{ $key }}" @if(old('billing_cycle', $membership->billing_cycle) === $key) selected @endif>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="text-right">
                    <x-primary-button>💾 Aktualisieren</x-primary-button>
                </div>
            </div>
        </form>
    </div>
@endsection
