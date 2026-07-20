@props([
    'title' => '',
    'icon' => '',
    'color' => 'blue' // z. B. blue, green, yellow, purple
])

@php
    $colorMap = [
        'blue' => 'border-blue-500 text-blue-600',
        'green' => 'border-green-500 text-green-600',
        'yellow' => 'border-yellow-500 text-yellow-600',
        'purple' => 'border-purple-500 text-purple-600',
        'red' => 'border-red-500 text-red-600',
        'indigo' => 'border-indigo-500 text-indigo-600',
    ];
    $colors = $colorMap[$color] ?? $colorMap['blue'];
@endphp

<section class="bg-white shadow-md rounded-xl p-6 border-l-4 {{ explode(' ', $colors)[0] }}">
    <h2 class="text-lg font-semibold {{ explode(' ', $colors)[1] }} mb-4">
        {{ $icon }} {{ $title }}
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{ $slot }}
    </div>
</section>
