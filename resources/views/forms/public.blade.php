@extends('layouts.public', [
    'title' => $form->title,
    'bodyClass' => 'min-h-screen bg-gray-50 text-gray-900',
])

@section('content')
    <main class="mx-auto max-w-3xl px-4 py-12">
        @include('forms.partials.public-form-content', ['embedded' => false])
    </main>
@endsection
