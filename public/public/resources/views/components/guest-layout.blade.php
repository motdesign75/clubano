<div class="min-h-screen flex flex-col justify-center items-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('login') }}">
            <x-application-logo class="w-20 h-20 fill-current text-blue-600" />
        </a>
    </div>

    <div class="w-full max-w-md mt-6 p-6 bg-white shadow-md rounded-lg">
        {{ $slot }}
    </div>
</div>
