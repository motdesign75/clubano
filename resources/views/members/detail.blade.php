@props([
    'label',
    'value' => '—',
    'link' => null,
])

<div class="flex flex-col">
    <span class="text-sm text-gray-500">{{ $label }}</span>
    @if($link)
        <a href="{{ $link }}" class="text-base font-medium text-[#2954A3] hover:underline">{{ $value ?: '—' }}</a>
    @else
        <span class="text-base font-medium text-gray-800">{{ $value ?: '—' }}</span>
    @endif
</div>
