@extends('layouts.app')

@section('content')
<div class="p-6">
    <h1 class="text-xl font-semibold mb-4">Neue Vorlage</h1>

    <form id="templateForm" method="POST" action="{{ route('templates.store') }}">
        @csrf

        <div class="bg-white rounded shadow p-6 space-y-4">
            @include('templates.form', ['template' => null, 'typeOptions' => $typeOptions ?? \App\Models\Template::typeOptions()])
        </div>
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
        height: 420,
        plugins: 'lists link image table code fullscreen',
        toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
    });

    const form = document.getElementById("templateForm");
    form.addEventListener("submit", function () {
        tinymce.triggerSave();
    });
});
</script>
@endpush
