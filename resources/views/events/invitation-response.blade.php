@extends('layouts.public', [
    'title' => 'Rückmeldung: ' . $event->title,
    'bodyClass' => 'min-h-screen bg-slate-50 text-slate-900',
    'robots' => 'noindex, nofollow',
])

@section('content')
<main class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-8 sm:px-6">
    <section class="w-full overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
        @if($event->image_url)
            <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="h-52 w-full object-cover sm:h-64">
        @endif

        <div class="p-6 sm:p-8">
            <div class="text-sm font-semibold text-indigo-700">{{ $event->tenant->name ?? 'Clubano' }}</div>
            <div class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Zu- oder Absage</div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $event->title }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">
                {{ $event->start->format('d.m.Y H:i') }} Uhr
                @if($event->location)
                    · {{ $event->location }}
                @endif
            </p>

            @if(session('success'))
                <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-7 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Eingeladen</div>
                <div class="mt-2 text-xl font-semibold text-slate-950">{{ $member->full_name }}</div>
                <div class="mt-2 text-sm text-slate-500">
                    Aktuelle Rückmeldung: <span class="font-semibold text-slate-900">{{ $invitation->statusLabel() }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('events.invitations.public.store', $invitation->response_token) }}" class="mt-7 space-y-5">
                @csrf

                <div>
                    <div class="text-sm font-semibold text-slate-950">Kannst du teilnehmen?</div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <label class="flex cursor-pointer items-center justify-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-center text-sm font-semibold text-emerald-900 has-[:checked]:ring-2 has-[:checked]:ring-emerald-500">
                            <input type="radio" name="status" value="{{ \App\Models\EventInvitation::STATUS_ACCEPTED }}" class="border-emerald-300 text-emerald-700 focus:ring-emerald-500" @checked(old('status', $invitation->status) === \App\Models\EventInvitation::STATUS_ACCEPTED)>
                            <span>Ich bin dabei</span>
                        </label>
                        <label class="flex cursor-pointer items-center justify-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-center text-sm font-semibold text-rose-900 has-[:checked]:ring-2 has-[:checked]:ring-rose-500">
                            <input type="radio" name="status" value="{{ \App\Models\EventInvitation::STATUS_DECLINED }}" class="border-rose-300 text-rose-700 focus:ring-rose-500" @checked(old('status', $invitation->status) === \App\Models\EventInvitation::STATUS_DECLINED)>
                            <span>Ich kann nicht</span>
                        </label>
                        <label class="flex cursor-pointer items-center justify-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-center text-sm font-semibold text-amber-900 has-[:checked]:ring-2 has-[:checked]:ring-amber-500">
                            <input type="radio" name="status" value="{{ \App\Models\EventInvitation::STATUS_MAYBE }}" class="border-amber-300 text-amber-700 focus:ring-amber-500" @checked(old('status', $invitation->status) === \App\Models\EventInvitation::STATUS_MAYBE)>
                            <span>Vielleicht</span>
                        </label>
                    </div>
                    @error('status')
                        <div class="mt-2 text-sm font-medium text-rose-700">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="note" class="text-sm font-semibold text-slate-950">Notiz</label>
                    <textarea id="note" name="note" rows="4" class="mt-2 w-full rounded-2xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300" placeholder="Optional, z. B. komme etwas später">{{ old('note', $invitation->note) }}</textarea>
                    @error('note')
                        <div class="mt-2 text-sm font-medium text-rose-700">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Rückmeldung speichern
                </button>
            </form>
        </div>
    </section>
</main>
@endsection
