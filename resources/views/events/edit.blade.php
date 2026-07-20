@extends('layouts.app')

@section('title', 'Veranstaltung bearbeiten')

@section('content')
<div class="py-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-10">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Veranstaltung bearbeiten</h1>
        <p class="mt-2 text-sm text-gray-600">Hier kannst du Titel, Beschreibung, Ort, Zeit, Foto und Buchbarkeit anpassen.</p>
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

    <form action="{{ route('events.update', $event) }}" method="POST" enctype="multipart/form-data"
          class="space-y-6 bg-white border border-gray-200 shadow-md rounded-2xl p-6 sm:p-8">
        @csrf
        @method('PUT')

        @include('events.partials.form-fields', ['event' => $event])

        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-gray-100">
            <a href="{{ route('events.index') }}" class="text-sm text-gray-500 hover:text-blue-600 transition">
                ← Zurück zur Übersicht
            </a>

            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow transition">
                Änderungen speichern
            </button>
        </div>
    </form>

    <div class="mt-10 bg-white border border-gray-200 rounded-2xl p-6 shadow-md space-y-4">
        @php
            $publicEventUrl = route('events.public.show', $event->id);
            $bookingUrl = $event->activeBookingForm ? route('forms.public.show', $event->activeBookingForm->slug) : null;
            $publicListUrl = route('events.public.index', $event->tenant->slug);
            $embedListUrl = route('events.public.embed', $event->tenant->slug);
            $selectedCategoryEmbed = $event->category ? $embedListUrl.'?category='.$event->category->slug : null;
        @endphp

        <h2 class="text-lg font-semibold text-gray-800">Öffentliche Eventseite</h2>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Öffentlicher Link</label>
                <input type="text" readonly value="{{ $publicEventUrl }}" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Buchungslink</label>
                <input type="text" readonly value="{{ $bookingUrl ?? 'Wird aktiv, sobald Buchbarkeit eingeschaltet ist.' }}" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Veranstaltungsliste</label>
                <input type="text" readonly value="{{ $publicListUrl }}" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Embed-Liste</label>
                <input type="text" readonly value="{{ $selectedCategoryEmbed ?? $embedListUrl }}" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Iframe-Code für Website</label>
            <textarea readonly rows="4" class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 text-sm">{{ '<iframe src="' . ($selectedCategoryEmbed ?? $embedListUrl) . '" width="100%" height="980" style="border:0;max-width:100%;" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>' }}</textarea>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ $publicEventUrl }}" target="_blank" class="inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Öffentliche Vorschau
            </a>

            <a href="{{ $publicListUrl }}" target="_blank" class="inline-flex rounded-lg border border-blue-200 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                Veranstaltungsseite ansehen
            </a>

            @if($bookingUrl)
                <a href="{{ $bookingUrl }}" target="_blank" class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Buchungsformular öffnen
                </a>

                <a href="{{ route('forms.submissions', $event->activeBookingForm) }}" class="inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Anmeldungen ansehen
                </a>
            @endif
        </div>
    </div>

    @include('events.partials.schedule', [
        'event' => $event,
        'eventShifts' => $eventShifts,
        'assignableMembers' => $assignableMembers,
        'scheduleStats' => $scheduleStats,
    ])

    @include('events.partials.participants', [
        'event' => $event,
        'eventBookings' => $eventBookings,
        'bookingSubmissionCount' => $bookingSubmissionCount,
        'participantCount' => $participantCount,
        'bookingRevenue' => $bookingRevenue,
    ])
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

    document.querySelector('form[action="{{ route('events.update', $event) }}"]')?.addEventListener('submit', function () {
        tinymce.triggerSave();
    });
});
</script>
@endpush
