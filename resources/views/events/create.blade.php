@extends('layouts.app')

@section('title', 'Termin planen')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Event-Editor</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Termin planen</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Erst der Kern, dann Zeit und Ort, dann Sichtbarkeit und Anmeldung. So entsteht ein sauberer Termin.
                </p>
            </div>
            <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                Zum Kalender
            </a>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte prüfe die markierten Felder.
        </div>
    @endif

    <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        @include('events.partials.form-fields', ['event' => $event])

        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Abbrechen
            </a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                <x-heroicon-o-check-circle class="h-5 w-5" />
                Termin speichern
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
        height: 280,
        menubar: false,
        plugins: 'lists link table code fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | code fullscreen',
        content_style: 'body { font-family: Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.6; }'
    });

    document.querySelector('form[action="{{ route('events.store') }}"]')?.addEventListener('submit', function () {
        tinymce.triggerSave();
    });
});
</script>
@endpush
