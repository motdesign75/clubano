@extends('layouts.app')

@section('title', 'Buchung bearbeiten')

@section('content')
    <div class="max-w-2xl space-y-6">
        <h1 class="text-2xl font-bold text-gray-800">✏️ Buchung bearbeiten</h1>

        <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="bg-white rounded shadow p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="date" class="block text-sm font-medium text-gray-700">Datum *</label>
                <input type="date" name="date" id="date"
                       value="{{ old('date', $transaction->date->format('Y-m-d')) }}"
                       class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                       required />
                @error('date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Beschreibung *</label>
                <input type="text" name="description" id="description"
                       value="{{ old('description', $transaction->description) }}"
                       class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                       required />
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700">Betrag (in €) *</label>
                <input type="number" name="amount" id="amount"
                       value="{{ old('amount', $transaction->amount) }}"
                       step="0.01" min="0"
                       class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                       required />
                @error('amount') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="account_from_id" class="block text-sm font-medium text-gray-700">Von-Konto *</label>
                <select name="account_from_id" id="account_from_id"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        required>
                    <option value="">– Bitte wählen –</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}"
                            {{ old('account_from_id', $transaction->account_from_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->number ? $account->number . ' – ' : '' }}{{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('account_from_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="account_to_id" class="block text-sm font-medium text-gray-700">Nach-Konto *</label>
                <select name="account_to_id" id="account_to_id"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        required>
                    <option value="">– Bitte wählen –</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}"
                            {{ old('account_to_id', $transaction->account_to_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->number ? $account->number . ' – ' : '' }}{{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('account_to_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-between mt-6">
                <a href="{{ route('transactions.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
                    ← Zurück zur Übersicht
                </a>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">
                    💾 Änderungen speichern
                </button>
            </div>
        </form>
    </div>
@endsection
