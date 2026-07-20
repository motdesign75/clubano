@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => '',
    'required' => false,
])

@php
    $selectId = $attributes->get('id') ?? $name;
    $errorId = $errors->has($name) ? $name . '_error' : null;
@endphp

<div class="space-y-1">
    @if($label)
        <label for="{{ $selectId }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}@if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        @if($required) required @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if($errorId) aria-describedby="{{ $errorId }}" @endif
        {{ $attributes->merge([
            'class' => 'mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-[#2954A3] focus:ring-[#2954A3]' . ($errors->has($name) ? ' border-red-500' : '')
        ]) }}
    >
        <option value="">– bitte wählen –</option>

        @foreach($options as $key => $text)
            <option value="{{ $key }}" {{ (string)$key === (string)old($name, $selected) ? 'selected' : '' }}>
                {{ $text }}
            </option>
        @endforeach
    </select>

    @if($errors->has($name))
        <p id="{{ $errorId }}" class="mt-2 text-sm text-red-600">
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
