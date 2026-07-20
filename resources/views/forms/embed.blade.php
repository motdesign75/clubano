@extends('layouts.public', [
    'title' => $form->title,
    'bodyClass' => 'bg-transparent text-gray-900',
    'robots' => 'noindex, nofollow',
])

@section('content')
    <main class="mx-auto max-w-3xl px-3 py-3">
        @include('forms.partials.public-form-content', ['embedded' => true])
    </main>
@endsection
