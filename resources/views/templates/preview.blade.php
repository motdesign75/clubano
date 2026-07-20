@extends('layouts.app')
@section('content')

<div class="p-6">

    <h1 class="text-xl font-semibold mb-4">
        Vorschau Vorlage
    </h1>

    <div class="bg-white rounded shadow p-4">

        <iframe
            style="width:100%; height:800px; border:1px solid #ccc; border-radius:6px;"
            srcdoc="{{ str_replace('"', '&quot;', $text) }}">
        </iframe>

    </div>

    <div class="mt-4">

        <a href="{{ route('templates.index') }}"
           class="bg-gray-600 text-white px-4 py-2 rounded">

            Zurück

        </a>

    </div>

</div>

@endsection