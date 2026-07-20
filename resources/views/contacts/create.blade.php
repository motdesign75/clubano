@extends('layouts.app')

@section('content')
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight">
            Neuen Kontakt anlegen
        </h2>
    </x-slot>

    <div class="max-w-5xl mx-auto mt-6 px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('contacts.store') }}">
            @csrf

            @include('contacts._form')
        </form>
    </div>
@endsection
