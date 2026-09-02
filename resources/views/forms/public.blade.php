@extends('layouts.public', [
    'title' => $form->title,
    'bodyClass' => 'min-h-screen bg-slate-100 text-slate-950 antialiased',
])

@section('content')
    <main class="mx-auto max-w-5xl px-4 py-5 sm:px-6 sm:py-10 lg:py-14">
        @include('forms.partials.public-form-content', ['embedded' => false])
    </main>
@endsection
