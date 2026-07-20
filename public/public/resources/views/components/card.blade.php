@props(['title' => null])

<div class="bg-white shadow-sm rounded-lg p-6">
    @if($title)
        <h2 class="text-lg font-semibold text-primary mb-4">{{ $title }}</h2>
    @endif
    {{ $slot }}
</div>
