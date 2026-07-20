@extends('layouts.app')

@section('title', 'Neue Mitgliedschaft')

@section('content')
    <div class="max-w-xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">➕ Neue Mitgliedschaft</h1>

        <form method="POST" action="{{ route('memberships.store') }}">
            @csrf

            <div class="space-y-4 bg-white p-6 rounded shadow">
                <div>
                    <label class="block font-medium text-sm text-gray-700">Bezeichnung</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full mt-1 border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Beitrag (€)</label>
                    <input type="number" name="fee" step="0.01" min="0" value="{{ old('fee') }}" class="w-full mt-1 border rounded px-3 py-2">
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Abrechnung</label>
                    <select name="billing_cycle" required class="w-full mt-1 border rounded px-3 py-2">
                        <option value="monatlich">monatlich</option>
                        <option value="quartalsweise">vierteljährlich</option>
                        <option value="halbjährlich">halbjährlich</option>
                        <option value="jährlich" selected>jährlich</option>
                    </select>
                </div>

                <div class="text-right">
                    <x-primary-button>💾 Speichern</x-primary-button>
                </div>
            </div>
        </form>
    </div>
@endsection
