@extends('layouts.app')

@section('content')
<div class="space-y-6 p-6" x-data="letterSendPage()">
    <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-500">Briefversand</div>
                <h1 class="mt-2 text-3xl font-semibold text-slate-900">Serienbriefe mit Briefbogen erstellen</h1>
                <p class="mt-2 text-sm text-slate-500">Nutzt dieselben Vorlagen wie im Mailbereich, aber als druckfertige Brief-PDF. Wenn im Vereinsprofil ein Briefbogen hinterlegt ist, wird er automatisch verwendet.</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="font-semibold">Briefbogen</div>
                <div class="mt-1 text-amber-800/80">{{ auth()->user()->tenant->use_letterhead && auth()->user()->tenant->pdf_template ? 'Aktiv – wird in der PDF eingeblendet.' : 'Noch nicht aktiv – der Brief wird ohne Briefbogen erzeugt.' }}</div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">Bitte prüfe den Briefversand noch einmal.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('letters.generate') }}" class="grid gap-6 xl:grid-cols-[0.96fr_1.04fr]">
        @csrf
        <div class="space-y-6">
            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Schritt 1</div>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Vorlage auswählen</h2>
                    </div>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Brief oder Mail & Brief</span>
                </div>
                <div class="mt-5 space-y-3">
                    <label for="template_id" class="text-sm font-medium text-slate-700">Vorlage</label>
                    <select id="template_id" name="template_id" x-model="selectedTemplateId" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" {{ (string) old('template_id', $selectedTemplateId) === (string) $template->id ? 'selected' : '' }}>{{ $template->name }} · {{ $template->typeLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Schritt 2</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Empfänger festlegen</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <label class="cursor-pointer rounded-2xl border px-4 py-3 text-sm transition" :class="recipientType === 'member' ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-600'"><input type="radio" name="recipient_type" value="member" x-model="recipientType" class="sr-only">Mitglieder</label>
                    <label class="cursor-pointer rounded-2xl border px-4 py-3 text-sm transition" :class="recipientType === 'contact' ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-600'"><input type="radio" name="recipient_type" value="contact" x-model="recipientType" class="sr-only">Kontakte</label>
                    <label class="cursor-pointer rounded-2xl border px-4 py-3 text-sm transition" :class="recipientType === 'free' ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-200 bg-white text-slate-600'"><input type="radio" name="recipient_type" value="free" x-model="recipientType" class="sr-only">Freie Adresse</label>
                </div>

                <div class="mt-5" x-show="recipientType === 'member'">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <input type="search" x-model="memberSearch" placeholder="Mitglied suchen" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                        <button type="button" @click="selectAll('member')" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600">Alle</button>
                    </div>
                    <div class="max-h-80 overflow-y-auto rounded-2xl border border-slate-200">
                        @foreach($members as $member)
                            <label x-show="matchesMember('{{ strtolower($member->full_name) }}', '{{ strtolower($member->email ?? '') }}')" class="flex cursor-pointer items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50">
                                <span><span class="block text-sm font-medium text-slate-900">{{ $member->full_name }}</span><span class="block text-xs text-slate-500">{{ $member->street }} · {{ $member->zip }} {{ $member->city }}</span></span>
                                <input type="checkbox" name="members[]" value="{{ $member->id }}" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500" {{ in_array($member->id, old('members', $preselectedMembers), true) ? 'checked' : '' }}>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5" x-show="recipientType === 'contact'">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <input type="search" x-model="contactSearch" placeholder="Kontakt suchen" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                        <button type="button" @click="selectAll('contact')" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600">Alle</button>
                    </div>
                    <div class="max-h-80 overflow-y-auto rounded-2xl border border-slate-200">
                        @foreach($contacts as $contact)
                            <label x-show="matchesContact('{{ strtolower($contact->display_name) }}', '{{ strtolower($contact->primary_email ?? '') }}')" class="flex cursor-pointer items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50">
                                <span><span class="block text-sm font-medium text-slate-900">{{ $contact->display_name }}</span><span class="block text-xs text-slate-500">{{ $contact->street }} · {{ $contact->zip }} {{ $contact->city }}</span></span>
                                <input type="checkbox" name="contacts[]" value="{{ $contact->id }}" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500" {{ in_array($contact->id, old('contacts', $preselectedContacts), true) ? 'checked' : '' }}>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2" x-show="recipientType === 'free'">
                    <div><label class="mb-2 block text-sm font-medium text-slate-700">Name</label><input type="text" name="free_name" value="{{ old('free_name') }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"></div>
                    <div><label class="mb-2 block text-sm font-medium text-slate-700">Organisation / Firma</label><input type="text" name="free_organization" value="{{ old('free_organization') }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"></div>
                    <div class="sm:col-span-2"><label class="mb-2 block text-sm font-medium text-slate-700">Anredezeile</label><input type="text" name="free_salutation" value="{{ old('free_salutation') }}" placeholder="z. B. Guten Tag Frau Beispiel" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"></div>
                    <div class="sm:col-span-2"><label class="mb-2 block text-sm font-medium text-slate-700">Straße</label><input type="text" name="free_street" value="{{ old('free_street') }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"></div>
                    <div><label class="mb-2 block text-sm font-medium text-slate-700">PLZ</label><input type="text" name="free_zip" value="{{ old('free_zip') }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"></div>
                    <div><label class="mb-2 block text-sm font-medium text-slate-700">Ort</label><input type="text" name="free_city" value="{{ old('free_city') }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"></div>
                    <div class="sm:col-span-2"><label class="mb-2 block text-sm font-medium text-slate-700">Land</label><input type="text" name="free_country" value="{{ old('free_country', 'Deutschland') }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"></div>
                </div>
            </section>
            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600">Brief-PDF erstellen</button>
        </div>
        <aside class="space-y-6">
            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Vorschau</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Ausgewählte Vorlage</h2>
                <div class="mt-4 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Betreff</div>
                    <div class="mt-2 text-base font-semibold text-slate-900" x-text="currentSubject || 'Ohne Betreffzeile'"></div>
                    <div class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Inhalt</div>
                    <div class="prose prose-sm mt-3 max-w-none text-slate-700" x-html="currentBody"></div>
                </div>
            </section>
        </aside>
    </form>
</div>
@endsection

@push('scripts')
<script>
function letterSendPage() {
    return {
        selectedTemplateId: @js((string) old('template_id', $selectedTemplateId)),
        recipientType: @js(old('recipient_type', $preselectedContacts ? 'contact' : ($preselectedMembers ? 'member' : 'member'))),
        memberSearch: '',
        contactSearch: '',
        templates: @js($templates->map(fn ($template) => [
            'id' => (string) $template->id,
            'subject' => $template->subject,
            'body' => $template->body,
        ])->values()),
        get currentTemplate() {
            return this.templates.find((template) => template.id === this.selectedTemplateId) || this.templates[0] || null;
        },
        get currentSubject() {
            return this.currentTemplate?.subject || '';
        },
        get currentBody() {
            return this.currentTemplate?.body || '<p class="text-slate-400">Keine Vorlage ausgewählt.</p>';
        },
        matchesMember(name, email) {
            const term = this.memberSearch.toLowerCase().trim();
            return !term || name.includes(term) || email.includes(term);
        },
        matchesContact(name, email) {
            const term = this.contactSearch.toLowerCase().trim();
            return !term || name.includes(term) || email.includes(term);
        },
        selectAll(type) {
            const selector = type === 'member' ? 'input[name="members[]"]' : 'input[name="contacts[]"]';
            document.querySelectorAll(selector).forEach((input) => {
                input.checked = true;
            });
        },
    }
}
</script>
@endpush
