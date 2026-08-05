@extends('layouts.app')

@section('title', 'Neuen Benutzer anlegen')

@section('content')
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-slate-900">Neuen Benutzer anlegen</h1>
            <p class="text-sm text-slate-500">Name, Zugang und eine klare Rolle. Mehr braucht es im Alltag nicht.</p>
        </div>

        @if ($errors->any())
            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-700">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('error'))
            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('users.store') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input name="name" type="text" required class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">E-Mail</label>
                <input name="email" type="email" required class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Passwort</label>
                    <input name="password" type="password" required class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Passwort bestätigen</label>
                    <input name="password_confirmation" type="password" required class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                </div>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Rolle</label>
                    <select name="role" required class="mt-1 w-full rounded-xl border-gray-300 text-sm shadow-sm">
                        @foreach($roleOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-3 md:grid-cols-{{ count($roleOptions) > 2 ? '3' : '2' }}">
                    @foreach($roleOptions as $option)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-sm font-semibold text-slate-900">{{ $option['label'] }}</div>
                            <p class="mt-2 text-xs leading-5 text-slate-600">{{ $option['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="inline-flex rounded-xl bg-indigo-600 px-5 py-3 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Benutzer speichern</button>
            </div>
        </form>
    </div>
</div>
@endsection
