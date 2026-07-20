@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-3xl">
            <div class="text-sm text-slate-500">
                <a href="{{ route('tasks.index') }}" class="hover:text-slate-700 hover:underline">Aufgaben</a>
                <span class="mx-1">›</span>
                <span>Bearbeiten</span>
                @if($selectedProject)
                    <span class="mx-1">·</span>
                    <span>Projekt: {{ $selectedProject->name }}</span>
                @endif
            </div>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">{{ $pageTitle }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $pageIntro }}</p>
        </div>

        <a href="{{ $backUrl }}"
           class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Zurück
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900 shadow-sm">
            <div class="font-semibold">Bitte kurz prüfen.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php($method = 'PUT')
    @include('tasks._form')
</div>
@endsection
