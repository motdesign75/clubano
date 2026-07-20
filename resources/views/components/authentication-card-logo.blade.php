@props([
    'size' => 'h-16',
])

<div class="flex justify-center">
    <a href="/" class="flex items-center justify-center group">
        <img 
            src="{{ asset('images/clubano.svg') }}"
            alt="Clubano"
            class="{{ $size }} w-auto transition duration-300 group-hover:scale-105 group-hover:opacity-90"
        >
    </a>
</div>