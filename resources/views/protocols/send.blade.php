@extends('layouts.app')

@section('title', 'Protokoll versenden')
@section('help-key', 'protocols.send')

@php
    $protocolAttachments = $protocol->attachments ?? $protocol->attachment_paths ?? [];

    if (is_string($protocolAttachments)) {
        $protocolAttachments = [$protocolAttachments];
    }
@endphp

@section('content')
<div x-data="protocolSendPage()" x-init="init()" class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Protokollversand</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $protocol->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Das erzeugte Protokoll-PDF wird immer mitgesendet. Bestehende Protokoll-Anhänge gehen automatisch mit in dieselbe Mail.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200">
                <div class="font-semibold text-white">{{ count($protocolAttachments) + 1 }} Datei(en) im Versand</div>
                <div class="mt-0.5 text-xs text-slate-300">1 PDF + {{ count($protocolAttachments) }} gespeicherte Anhänge</div>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
            <div class="font-semibold">Bitte prüfe den Versand noch einmal.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('protocols.mail.send', $protocol) }}" class="grid gap-6 xl:grid-cols-3">
        @csrf

        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Empfänger</div>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">An wen soll das Protokoll gehen?</h2>
                        <p class="mt-2 text-sm text-slate-500">Mitglieder, Kontakte und freie Mailadressen lassen sich hier in einem Schritt kombinieren.</p>
                    </div>
                    <div class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                        <span x-text="selectedCount"></span> ausgewählt
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="button" @click="selectAll('.member-checkbox, .contact-checkbox')" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Alle markieren
                    </button>
                    <button type="button" @click="unselectAll('.member-checkbox, .contact-checkbox')" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Alles lösen
                    </button>
                </div>

                <div class="mt-5 space-y-5">
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Mitglieder</div>
                                <div class="mt-1 text-sm text-slate-500">Alle Mitglieder mit hinterlegter E-Mail-Adresse.</div>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <input type="text" x-model="memberSearch" placeholder="Mitglieder suchen..." class="w-full rounded-full border-slate-300 px-4 py-2 text-sm sm:w-64">
                                <button type="button" @click="selectAll('.member-checkbox')" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Alle Mitglieder
                                </button>
                            </div>
                        </div>

                        <div class="max-h-[280px] overflow-y-auto">
                            @forelse($members as $member)
                                <label x-show="matchesMember(@js(strtolower($member->full_name . ' ' . ($member->email ?? ''))))" class="flex cursor-pointer items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 hover:bg-slate-50">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-900">{{ $member->full_name }}</span>
                                        <span class="mt-1 block truncate text-xs text-slate-500">{{ $member->email }}</span>
                                    </span>
                                    <input type="checkbox" name="members[]" value="{{ $member->id }}" class="member-checkbox h-4 w-4 rounded border-slate-300 text-slate-900" @change="updateCount()">
                                </label>
                            @empty
                                <div class="px-4 py-6 text-sm text-slate-500">Keine Mitglieder mit E-Mail-Adresse gefunden.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Kontakte</div>
                                <div class="mt-1 text-sm text-slate-500">Externe Ansprechpartner und Organisationen direkt mitversenden.</div>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <input type="text" x-model="contactSearch" placeholder="Kontakte suchen..." class="w-full rounded-full border-slate-300 px-4 py-2 text-sm sm:w-64">
                                <button type="button" @click="selectAll('.contact-checkbox')" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Alle Kontakte
                                </button>
                            </div>
                        </div>

                        <div class="max-h-[280px] overflow-y-auto">
                            @forelse($contacts as $contact)
                                <label x-show="matchesContact(@js(strtolower($contact->display_name . ' ' . ($contact->primary_email ?? ''))))" class="flex cursor-pointer items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 hover:bg-slate-50">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-900">{{ $contact->display_name }}</span>
                                        <span class="mt-1 block truncate text-xs text-slate-500">{{ $contact->primary_email }}</span>
                                    </span>
                                    <input type="checkbox" name="contacts[]" value="{{ $contact->id }}" class="contact-checkbox h-4 w-4 rounded border-slate-300 text-slate-900" @change="updateCount()">
                                </label>
                            @empty
                                <div class="px-4 py-6 text-sm text-slate-500">Keine Kontakte mit E-Mail-Adresse gefunden.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <label for="direct_emails" class="block text-sm font-medium text-slate-700">Freie E-Mail-Adressen</label>
                    <p class="mt-1 text-sm text-slate-500">Eine Adresse pro Zeile oder durch Komma bzw. Semikolon getrennt.</p>
                    <textarea id="direct_emails" name="direct_emails" rows="4" x-model="directEmails" @input="updateCount()" class="mt-3 w-full rounded-2xl border-slate-300 text-sm" placeholder="info@example.org&#10;vorstand@example.org">{{ old('direct_emails') }}</textarea>
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Protokoll jetzt senden
                </button>
                <a href="{{ route('protocols.show', $protocol) }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Zurück zum Protokoll
                </a>
            </div>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Versandinhalt</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Was in der Mail steckt</h2>

                <div class="mt-5 space-y-3">
                    <div class="border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                        <div class="text-sm font-semibold text-slate-900">Protokoll-PDF</div>
                        <div class="mt-1 text-sm text-slate-500">Wird automatisch aus dem aktuellen Protokoll erzeugt und angehängt.</div>
                    </div>

                    @forelse($protocolAttachments as $file)
                        <div class="border-t border-slate-100 py-3">
                            <div class="text-sm font-semibold text-slate-900">{{ basename($file) }}</div>
                            <div class="mt-1 text-xs text-slate-500">Bereits am Protokoll gespeichert und wird mitgesendet.</div>
                        </div>
                    @empty
                        <div class="border-t border-slate-100 py-3 text-sm text-slate-500">
                            Zusätzlich zur PDF sind aktuell keine weiteren Protokoll-Anhänge gespeichert.
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </form>
</div>

<script>
    function protocolSendPage() {
        return {
            memberSearch: '',
            contactSearch: '',
            directEmails: @js(old('direct_emails', '')),
            selectedCount: 0,
            init() {
                this.updateCount();
            },
            selectAll(selector) {
                document.querySelectorAll(selector).forEach((element) => {
                    element.checked = true;
                });
                this.updateCount();
            },
            unselectAll(selector) {
                document.querySelectorAll(selector).forEach((element) => {
                    element.checked = false;
                });
                this.updateCount();
            },
            updateCount() {
                const memberCount = document.querySelectorAll('.member-checkbox:checked').length;
                const contactCount = document.querySelectorAll('.contact-checkbox:checked').length;
                const directCount = this.parsedDirectEmails().length;
                this.selectedCount = memberCount + contactCount + directCount;
            },
            matchesMember(text) {
                if (!this.memberSearch) return true;
                return text.includes(this.memberSearch.toLowerCase());
            },
            matchesContact(text) {
                if (!this.contactSearch) return true;
                return text.includes(this.contactSearch.toLowerCase());
            },
            parsedDirectEmails() {
                if (!this.directEmails) return [];
                return this.directEmails
                    .split(/[\n,;]+/)
                    .map((item) => item.trim())
                    .filter((item) => item.length > 0);
            }
        }
    }
</script>
@endsection
