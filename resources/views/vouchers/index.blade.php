@extends('layouts.app')

@section('title', 'Gutscheine')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Gutscheine</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Wert sichern. Einlösung kontrollieren.</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300 sm:text-base">
                    Jeder Gutschein hat einen Code, einen Restwert und eine nachvollziehbare Historie. Alte unnummerierte Gutscheine kannst du hier nacherfassen.
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('vouchers.check') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-5 text-sm font-semibold text-white hover:bg-white/10">
                    Gutschein prüfen
                </a>
                <a href="{{ route('vouchers.settings') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-5 text-sm font-semibold text-white hover:bg-white/10">
                    Vorlage einrichten
                </a>
                <a href="{{ route('vouchers.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-5 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    Gutschein anlegen
                </a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Aktive Gutscheine</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $stats['active_count'] }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Offener Wert</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format((float) $stats['open_value'], 2, ',', '.') }} €</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Altbestand</div>
            <div class="mt-2 text-3xl font-semibold text-slate-950">{{ $stats['legacy_count'] }}</div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" class="grid gap-3 lg:grid-cols-[1fr_220px_auto]">
            <input type="search" name="search" value="{{ $search }}" placeholder="Code, Käufer oder Empfänger suchen" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
            <select name="status" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                <option value="active" @selected($status === 'active')>Aktiv</option>
                <option value="redeemed" @selected($status === 'redeemed')>Eingelöst</option>
                <option value="expired" @selected($status === 'expired')>Abgelaufen</option>
                <option value="void" @selected($status === 'void')>Gesperrt</option>
                <option value="all" @selected($status === 'all')>Alle</option>
            </select>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Filtern
            </button>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
            <div class="hidden grid-cols-[170px_1fr_140px_140px_150px_130px_180px] bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid">
                <div>Code</div>
                <div>Gutschein</div>
                <div>Restwert</div>
                <div>Status</div>
                <div>Zustellung</div>
                <div>Einlösungen</div>
                <div>Aktion</div>
            </div>

            @forelse($vouchers as $voucher)
                <div class="grid gap-3 border-t border-slate-100 px-4 py-4 lg:grid-cols-[170px_1fr_140px_140px_150px_130px_180px] lg:items-center">
                    <div class="font-mono text-sm font-semibold text-slate-950">{{ $voucher->code }}</div>
                    <div>
                        <div class="font-semibold text-slate-950">
                            {{ $voucher->title }}
                            @if($voucher->legacy)
                                <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Altbestand</span>
                            @endif
                        </div>
                        <div class="mt-1 text-sm text-slate-500">
                            {{ $voucher->recipient_name ?: $voucher->buyer_name ?: 'Ohne Namen' }}
                            @if($voucher->issued_at)
                                · ausgegeben {{ $voucher->issued_at->format('d.m.Y') }}
                            @endif
                            @if($voucher->expires_at)
                                · gültig bis {{ $voucher->expires_at->format('d.m.Y') }}
                            @endif
                        </div>
                        @if($voucher->dedication_message)
                            <div class="mt-2 line-clamp-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                {{ $voucher->dedication_message }}
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-slate-950">{{ number_format((float) $voucher->remaining_amount, 2, ',', '.') }} €</div>
                        <div class="text-xs text-slate-500">von {{ number_format((float) $voucher->original_amount, 2, ',', '.') }} €</div>
                    </div>
                    <div>
                        <span class="inline-flex rounded-full {{ $voucher->is_redeemable ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }} px-3 py-1 text-xs font-semibold">
                            {{ $voucher->status_label }}
                        </span>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-slate-700">{{ $voucher->delivery_method_label }}</div>
                        @if($voucher->delivered_at)
                            <div class="text-xs text-slate-500">versendet {{ $voucher->delivered_at->format('d.m.Y H:i') }}</div>
                        @endif
                    </div>
                    <div class="text-sm text-slate-600">{{ $voucher->redemptions_count }}</div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('vouchers.download', $voucher) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            PDF
                        </a>
                        <form method="POST" action="{{ route('vouchers.send', $voucher) }}" class="flex min-w-0 flex-1 flex-col gap-1">
                            @csrf
                            <input type="email" name="email" value="{{ $voucher->recipient_email ?: $voucher->buyer_email }}" placeholder="E-Mail" class="min-w-0 rounded-lg border-slate-300 text-xs shadow-sm focus:border-slate-500 focus:ring-slate-300">
                            <textarea name="dedication_message" rows="2" maxlength="500" placeholder="Widmung optional" class="min-w-0 rounded-lg border-slate-300 text-xs shadow-sm focus:border-slate-500 focus:ring-slate-300">{{ $voucher->dedication_message }}</textarea>
                            <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800">Senden</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-slate-500">Noch keine Gutscheine vorhanden.</div>
            @endforelse
        </div>

        <div class="mt-5">
            {{ $vouchers->links() }}
        </div>
    </section>
</div>
@endsection
