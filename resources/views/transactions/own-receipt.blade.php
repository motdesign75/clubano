@extends('layouts.app')

@section('title', 'Eigenbeleg erstellen')

@section('content')
@php
    $currentMeta = $receiptMeta ?? [];
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Eigenbeleg erstellen</h1>
        <p class="text-sm text-slate-500">
            Wenn kein externer Beleg vorliegt, kannst du hier direkt einen vereinsinternen Eigenbeleg erzeugen.
        </p>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <form method="POST"
              action="{{ route('transactions.own-receipt.store', $transaction) }}"
              class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            @csrf

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="font-semibold">Das wird gleich erzeugt</div>
                <div class="mt-1 leading-6">
                    Clubano erstellt einen vereinsinternen PDF-Beleg, hängt ihn direkt an diese Buchung und markiert den Vorgang danach nicht mehr als fehlenden Beleg.
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Aussteller</label>
                    <input type="text" name="issuer_name" value="{{ old('issuer_name', $currentMeta['issuer_name'] ?? auth()->user()->name) }}" class="mt-2 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Funktion</label>
                    <input type="text" name="issuer_role" value="{{ old('issuer_role', $currentMeta['issuer_role'] ?? auth()->user()->roleLabel()) }}" class="mt-2 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Wofür wurde das Geld ausgegeben?</label>
                <textarea name="expense_reason" rows="4" class="mt-2 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required>{{ old('expense_reason', $currentMeta['expense_reason'] ?? $transaction->description) }}</textarea>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Warum liegt kein externer Beleg vor?</label>
                <textarea name="missing_receipt_reason" rows="4" class="mt-2 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900" required>{{ old('missing_receipt_reason', $currentMeta['missing_receipt_reason'] ?? '') }}</textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Ort</label>
                    <input type="text" name="location" value="{{ old('location', $currentMeta['location'] ?? $tenant->city) }}" class="mt-2 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Freigabe / geprüft von</label>
                    <input type="text" name="approved_by" value="{{ old('approved_by', $currentMeta['approved_by'] ?? '') }}" class="mt-2 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Zusätzliche Notizen</label>
                <textarea name="notes" rows="3" class="mt-2 w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes', $currentMeta['notes'] ?? '') }}</textarea>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-between">
                <a href="{{ route('transactions.edit', $transaction) }}"
                   class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Zurück zur Buchung
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                    Eigenbeleg erzeugen
                </button>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Buchung</div>
                <div class="mt-3 text-lg font-semibold text-slate-900">{{ $transaction->description }}</div>
                <div class="mt-2 text-sm text-slate-500">{{ $transaction->date->format('d.m.Y') }}</div>
                <div class="mt-4 text-2xl font-semibold text-slate-950">{{ number_format((float) $transaction->amount, 2, ',', '.') }} €</div>
                <div class="mt-3 text-sm text-slate-600">
                    {{ $transaction->account_from->name ?? '—' }} → {{ $transaction->account_to->name ?? '—' }}
                </div>

                @if($transaction->receipt_file)
                    <div class="mt-4">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('receipts.show', $transaction->receipt_file) }}"
                               target="_blank"
                               class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Aktuellen Beleg öffnen
                            </a>
                            @if($transaction->hasOwnReceipt())
                                <span class="inline-flex items-center justify-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700">
                                    Aktuell als Eigenbeleg hinterlegt
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-3xl border border-violet-200 bg-violet-50 p-5 text-sm text-violet-900 shadow-sm">
                <div class="font-semibold">Wann ist ein Eigenbeleg sinnvoll?</div>
                <div class="mt-2 leading-6">
                    Zum Beispiel bei verlorenen Belegen, kleinen Barauslagen oder Vorgängen, bei denen kein Fremdbeleg ausgestellt wurde.
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
