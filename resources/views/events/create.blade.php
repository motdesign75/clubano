@extends('layouts.app')

@section('title', 'Neue Veranstaltung')

@section('content')
<div class="py-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-10">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Neue Veranstaltung erstellen</h1>
        <p class="mt-2 text-sm text-gray-600">Plane ein neues Event, hinterlege ein Foto und entscheide direkt, ob die Veranstaltung buchbar sein soll.</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data"
          class="space-y-6 bg-white border border-gray-200 shadow-md rounded-2xl p-6 sm:p-8">
        @csrf

        @include('events.partials.form-fields', ['event' => $event])

        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-gray-100">
            <a href="{{ route('events.index') }}" class="text-sm text-gray-500 hover:text-blue-600 transition">
                ← Zurück zur Übersicht
            </a>

            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow transition">
                Veranstaltung speichern
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="/tinymce/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    tinymce.init({
        selector: '#description',
        license_key: 'gpl',
        height: 320,
        menubar: false,
        plugins: 'lists link table code fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link | code fullscreen',
        content_style: 'body { font-family: Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.6; }'
    });

    document.querySelector('form[action="{{ route('events.store') }}"]')?.addEventListener('submit', function () {
        tinymce.triggerSave();
    });
});
</script>
@endpush
