@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => '',
    'required' => false,
])

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}@if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-[#2954A3] focus:ring-[#2954A3]']) }}
    >
        <option value="">– bitte wählen –</option>

        @foreach($options as $key => $text)
            <option value="{{ $key }}" {{ (string)$key === (string)$value ? 'selected' : '' }}>{{ $text }}</option>
        @endforeach
    </select>
</div>
