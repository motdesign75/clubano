@extends('layouts.app')
@section('help-key', 'mail.create')

@section('content')
<div
    x-data="mailSendPage()"
    x-init="init()"
    class="mx-auto max-w-7xl space-y-6 px-4 py-6"
>
    <div class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Vorlagenversand</div>
        <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-3xl font-semibold">Serienmail senden</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-300">
                    Waehle eine Mail-Vorlage, pruefe die Vorschau und sende sie gezielt an Mitglieder, Kontakte oder freie E-Mail-Adressen.
                </p>
            </div>
            <div class="rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white">
                {{ $templates->count() }} Mail-Vorlagen verfuegbar
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(!empty($preselectedMemberIds))
        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-800">
            {{ count($preselectedMemberIds) }} Empfaenger wurden aus der Mitgliederliste vorausgewaehlt.
        </div>
    @endif

    @if(!empty($preselectedContactIds))
        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-800">
            {{ count($preselectedContactIds) }} Empfaenger wurden aus der Kontaktliste vorausgewaehlt.
        </div>
    @endif

    @if(!empty($preselectedDirectEmails))
        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-800">
            Freie E-Mail-Adressen wurden fuer den Versand vorausgefuellt.
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
            <div class="font-semibold">Bitte pruefe deine Eingaben.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('mail.send') }}" class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
        @csrf

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Vorlage</h2>
                        <p class="mt-1 text-sm text-slate-500">Nur Mail-Vorlagen stehen hier direkt fuer den Versand bereit.</p>
                    </div>
                    <a href="{{ route('templates.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">
                        Zu den Vorlagen
                    </a>
                </div>

                <div class="mt-5">
                    <label for="template_id" class="mb-1 block text-sm font-medium text-slate-700">Mail-Vorlage</label>
                    <select
                        id="template_id"
                        name="template_id"
                        x-model="templateId"
                        @change="updateTemplate()"
                        class="w-full rounded-2xl border-slate-300"
                    >
                        @foreach($templates as $template)
                            <option
                                value="{{ $template->id }}"
                                data-name="{{ $template->name }}"
                                data-subject="{{ $template->subject }}"
                                data-body="{{ base64_encode($template->body) }}"
                                @selected(old('template_id', $selectedTemplateId) == $template->id)
                            >
                                {{ $template->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Betreff</div>
                    <div class="mt-2 text-base font-semibold text-slate-900" x-text="templateSubject || 'Ohne Betreff'"></div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Empfaenger</h2>
                        <p class="mt-1 text-sm text-slate-500">Mitglieder, Kontakte und freie E-Mail-Adressen laufen in einem Versand. So musst du nicht zwischen Bereichen springen.</p>
                    </div>
                    <div class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                        <span x-text="selectedCount"></span> ausgewaehlt
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" @click="selectAll('.memberCheckbox, .contactCheckbox')" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Alle markieren
                    </button>
                    <button type="button" @click="unselectAll('.memberCheckbox, .contactCheckbox')" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Alles loesen
                    </button>
                </div>

                <div class="mt-5 space-y-5">
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Mitglieder</div>
                                <div class="mt-1 text-sm text-slate-500">Aktive Mitglieder schnell durchsuchen und auswaehlen.</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <input
                                    type="text"
                                    x-model="memberSearch"
                                    placeholder="Mitglieder suchen..."
                                    class="w-full rounded-full border-slate-300 px-4 py-2 text-sm sm:w-64"
                                >
                                <button type="button" @click="selectAll('.memberCheckbox')" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Alle Mitglieder
                                </button>
                            </div>
                        </div>
                        <div class="max-h-[320px] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    <tr>
                                        <th class="w-12 px-4 py-3"></th>
                                        <th class="px-4 py-3">Mitglied</th>
                                        <th class="px-4 py-3">E-Mail</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($members as $member)
                                        <tr
                                            class="border-t border-slate-100"
                                            x-show="matchesMemberSearch(@js(strtolower($member->full_name . ' ' . ($member->email ?? '') . ' ' . ($member->member_id ?? ''))))"
                                        >
                                            <td class="px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    class="memberCheckbox rounded border-slate-300 text-blue-600"
                                                    name="members[]"
                                                    value="{{ $member->id }}"
                                                    @checked(in_array($member->id, $preselectedMemberIds ?? []))
                                                    @change="updateCount()"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-slate-900">{{ $member->full_name }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $member->member_id ?? 'Ohne Mitgliedsnummer' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ $member->email ?: 'Keine E-Mail hinterlegt' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($member->email)
                                                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Versandfaehig</span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Ohne E-Mail</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Kontakte</div>
                                <div class="mt-1 text-sm text-slate-500">Firmen, Ansprechpartner und sonstige Kontakte direkt mit in denselben Versand nehmen.</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <input
                                    type="text"
                                    x-model="contactSearch"
                                    placeholder="Kontakte suchen..."
                                    class="w-full rounded-full border-slate-300 px-4 py-2 text-sm sm:w-64"
                                >
                                <button type="button" @click="selectAll('.contactCheckbox')" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Alle Kontakte
                                </button>
                            </div>
                        </div>
                        <div class="max-h-[320px] overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    <tr>
                                        <th class="w-12 px-4 py-3"></th>
                                        <th class="px-4 py-3">Kontakt</th>
                                        <th class="px-4 py-3">E-Mail</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($contacts as $contact)
                                        <tr
                                            class="border-t border-slate-100"
                                            x-show="matchesContactSearch(@js(strtolower($contact->display_name . ' ' . ($contact->primary_email ?? '') . ' ' . ($contact->city ?? ''))))"
                                        >
                                            <td class="px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    class="contactCheckbox rounded border-slate-300 text-blue-600"
                                                    name="contacts[]"
                                                    value="{{ $contact->id }}"
                                                    @checked(in_array($contact->id, $preselectedContactIds ?? []))
                                                    @change="updateCount()"
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-slate-900">{{ $contact->display_name }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ trim(($contact->zip ?? '') . ' ' . ($contact->city ?? '')) ?: 'Ohne Ort' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ $contact->primary_email ?: 'Keine E-Mail hinterlegt' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($contact->primary_email)
                                                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Versandfaehig</span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Ohne E-Mail</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <label for="direct_emails" class="block text-sm font-medium text-slate-700">Freie E-Mail-Adressen</label>
                    <p class="mt-1 text-sm text-slate-500">
                        Eine Adresse pro Zeile oder durch Komma/Semikolon getrennt. Diese Empfaenger muessen weder Mitglieder noch Kontakte sein.
                    </p>
                    <textarea
                        id="direct_emails"
                        name="direct_emails"
                        rows="5"
                        x-model="directEmails"
                        @input="updateCount()"
                        class="mt-3 w-full rounded-2xl border-slate-300 text-sm"
                        placeholder="max@example.org&#10;info@example.org"
                    >{{ old('direct_emails', $preselectedDirectEmails ?? '') }}</textarea>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button class="rounded-full bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Serienmail senden
                </button>
                <a href="{{ route('templates.index') }}" class="rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                    Zurueck zu Vorlagen
                </a>
            </div>
        </section>

        <aside class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Vorschau</h2>
                        <p class="mt-1 text-sm text-slate-500">Zur Orientierung mit dem Inhalt der gewaehlteten Vorlage.</p>
                    </div>
                    <template x-if="templateId">
                        <a :href="`{{ url('/templates') }}/${templateId}/preview`" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                            Gross anzeigen
                        </a>
                    </template>
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <div class="border-b border-slate-200 bg-white px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Betreff</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900" x-text="templateSubject || 'Ohne Betreff'"></div>
                    </div>
                    <div class="max-h-[620px] overflow-y-auto bg-white px-4 py-4">
                        <div class="prose prose-sm max-w-none text-slate-700" x-html="templateBody || '<p>Bitte eine Vorlage auswählen.</p>'"></div>
                    </div>
                </div>
            </div>
        </aside>
    </form>
</div>

<script>
    function mailSendPage() {
        return {
            templateId: @js(old('template_id', $selectedTemplateId)),
            templateSubject: '',
            templateBody: '',
            memberSearch: '',
            contactSearch: '',
            directEmails: @js(old('direct_emails', $preselectedDirectEmails ?? '')),
            selectedCount: 0,
            init() {
                this.updateTemplate();
                this.updateCount();
            },
            updateTemplate() {
                const select = document.getElementById('template_id');
                const option = select?.selectedOptions?.[0];
                if (!option) return;

                this.templateSubject = option.dataset.subject || '';
                const encoded = option.dataset.body || '';
                this.templateBody = encoded ? atob(encoded) : '';
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
                const memberCount = document.querySelectorAll('.memberCheckbox:checked').length;
                const contactCount = document.querySelectorAll('.contactCheckbox:checked').length;
                const directCount = this.parsedDirectEmails().length;
                this.selectedCount = memberCount + contactCount + directCount;
            },
            matchesMemberSearch(text) {
                if (!this.memberSearch) return true;
                return text.includes(this.memberSearch.toLowerCase());
            },
            matchesContactSearch(text) {
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
