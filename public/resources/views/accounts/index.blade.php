@extends('layouts.app')

@section('title', 'Kontenübersicht')

@section('content')
    <div class="space-y-10">
        <h1 class="text-2xl font-bold text-gray-800">📒 Kontenübersicht</h1>

        {{-- Barkonten --}}
        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-gray-700">💶 Barkonten (Bank & Kasse)</h2>

            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                @forelse($balanceAccounts as $account)
                    <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between hover:ring-2 hover:ring-blue-300 transition" role="region" aria-label="{{ $account->name }}">
                        <div class="flex items-center gap-3">
                            @if($account->type === 'bank')
                                <div class="text-3xl">🏦</div>
                            @else
                                <div class="text-3xl">💶</div>
                            @endif
                            <div>
                                <div class="font-semibold">{{ $account->name }}</div>
                                @if($account->number)
                                    <div class="text-sm text-gray-500">{{ $account->number }}</div>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('accounts.edit', $account) }}" class="text-blue-600 hover:underline text-sm">Bearbeiten</a>
                    </div>
                @empty
                    <p class="text-gray-600 text-sm">Keine Barkonten vorhanden.</p>
                @endforelse
            </div>
        </section>

        {{-- Kontenrahmen --}}
        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-gray-700">📊 Kontenrahmen (Einnahmen & Ausgaben)</h2>

            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                @forelse($chartAccounts as $account)
                    <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between hover:ring-2 hover:ring-green-300 transition" role="region" aria-label="{{ $account->name }}">
                        <div class="flex items-center gap-3">
                            @if($account->type === 'einnahme')
                                <div class="text-3xl">➕</div>
                            @else
                                <div class="text-3xl">➖</div>
                            @endif
                            <div>
                                <div class="font-semibold">{{ $account->name }}</div>
                                @if($account->number)
                                    <div class="text-sm text-gray-500">{{ $account->number }}</div>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('accounts.edit', $account) }}" class="text-blue-600 hover:underline text-sm">Bearbeiten</a>
                    </div>
                @empty
                    <p class="text-gray-600 text-sm">Keine Einnahmen-/Ausgabenkonten vorhanden.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
