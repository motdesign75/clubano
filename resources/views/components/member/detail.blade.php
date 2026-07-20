@props([
    'label',
    'value' => '—',
    'link' => null,
])

<dl
    class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-3"
    role="group"
    aria-labelledby="{{ Str::slug($label) }}-label"
>
    <dt id="{{ Str::slug($label) }}-label" class="text-sm text-gray-600 shrink-0 w-40">
        {{ $label }}
    </dt>
    <dd class="text-base font-medium break-words">
        @if($link)
            <a href="{{ $link }}"
               class="text-[#2954A3] hover:underline focus:outline-none focus:ring-2 focus:ring-[#2954A3] focus:ring-offset-2"
               aria-label="{{ $label }}: {{ $value }}">
                {{ $value ?: '—' }}
            </a>
        @else
            <span class="text-gray-900">{{ $value ?: '—' }}</span>
        @endif
    </dd>
</dl>
