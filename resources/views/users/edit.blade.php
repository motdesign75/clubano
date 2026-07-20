@extends('layouts.app')

@section('title', 'Benutzer bearbeiten')

@section('content')
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Benutzer bearbeiten</h1>
            <p class="mt-1 text-sm text-slate-500">Stammdaten, Rolle und optional das Passwort anpassen.</p>
        </div>

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-700">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input name="name" type="text" required value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">E-Mail</label>
                <input name="email" type="email" required value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Neues Passwort</label>
                    <input name="password" type="password" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                    <p class="mt-1 text-xs text-gray-500">Leer lassen, wenn das Passwort unverändert bleiben soll.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Passwort bestätigen</label>
                    <input name="password_confirmation" type="password" class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                </div>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Rolle</label>
                    <select name="role" required class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm">
                        @foreach($roleOptions as $option)
                            <option value="{{ $option['value'] }}" @selected(\App\Models\User::normalizeRole(old('role', $user->role)) === \App\Models\User::normalizeRole($option['value']))>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-3 md:grid-cols-{{ count($roleOptions) > 2 ? '3' : '2' }}">
                    @foreach($roleOptions as $option)
                        <div class="rounded-2xl border {{ \App\Models\User::normalizeRole(old('role', $user->role)) === \App\Models\User::normalizeRole($option['value']) ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-slate-50' }} p-4">
                            <div class="text-sm font-semibold text-slate-900">{{ $option['label'] }}</div>
                            <p class="mt-2 text-xs leading-5 text-slate-600">{{ $option['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    ← Zurück zur Benutzerliste
                </a>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    Änderungen speichern
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
