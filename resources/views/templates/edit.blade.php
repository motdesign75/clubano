@extends('layouts.app')

@section('content')

<div class="p-6">

    <h1 class="text-xl font-semibold mb-4">
        Vorlage bearbeiten
    </h1>

    @if ($errors->any())
        <div class="mb-4 bg-red-100 text-red-800 p-3 rounded">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="templateForm"
          method="POST"
          action="{{ route('templates.update', $template->id) }}">

        @csrf
        @method('PUT')

        @include('templates.form')

    </form>

</div>

@endsection



@push('scripts')

<script src="/tinymce/tinymce.min.js"></script>

<script>

document.addEventListener("DOMContentLoaded", function () {

    tinymce.init({

        selector: '#body',

        license_key: 'gpl',

        height: 400,

        plugins: 'lists link image table code fullscreen',

        toolbar:
        'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',

    });


    const form = document.getElementById("templateForm");

    form.addEventListener("submit", function () {

        tinymce.triggerSave();

    });

});

</script>

@endpush