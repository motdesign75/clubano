@props([
    'name',
    'label' => '',
    'value' => '',
    'type' => 'text',
    'required' => false,
    'ariaDescribedby' => null,
])

@php
    $inputId = $attributes->get('id') ?? $name;
    $errorId = $errors->has($name) ? $name . '_error' : null;
    $describedby = collect([$ariaDescribedby, $errorId])->filter()->implode(' ');
@endphp

<div>
    @if($label)
        <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700">
            {{ $label }} @if($required)<span class="text-red-600">*</span>@endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $inputId }}"
        value="{{ old($name, $value) }}"
        @if($required) required @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if($describedby) aria-describedby="{{ $describedby }}" @endif
        {{ $attributes->merge([
            'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 ' . ($errors->has($name) ? 'border-red-500' : '')
        ]) }}
    >

    @if($errors->has($name))
        <p id="{{ $errorId }}" class="mt-2 text-sm text-red-600">
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
