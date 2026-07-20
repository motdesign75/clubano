@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => '',
    'required' => false,
    'placeholder' => '',
])

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}@if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-[#2954A3] focus:ring-[#2954A3]']) }}
    >
</div>
