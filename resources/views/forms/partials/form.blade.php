<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="mt-4">
        <h1 class="text-2xl font-bold text-gray-800">
            {{ $form->exists ? 'Formular bearbeiten' : 'Neues Formular' }}
        </h1>
        <p class="text-sm text-gray-500">Oeffentliche Formulare per Link fuer Beitritt, Kontakt und Event-Anmeldungen.</p>
    </div>

    @unless($form->exists)
        <div class="rounded-2xl border border-indigo-100 bg-indigo-50 px-5 py-4 text-sm text-indigo-900">
            <div class="font-semibold">Schneller starten statt bei null anfangen</div>
            <div class="mt-2 text-indigo-800">
                Nach dem Anlegen bereitet Clubano automatisch ein passendes Starter-Set an Feldern vor:
                Kontakt, Beitritt, Event oder allgemeine Anfrage. Danach musst du meist nur noch feinjustieren.
            </div>
        </div>
    @endunless

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ $submitRoute }}" method="POST" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 space-y-6" x-data="{ confirmationMailEnabled: {{ old('confirmation_mail_enabled', $form->confirmation_mail_enabled) ? 'true' : 'false' }} }">
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Titel</label>
                <input type="text" name="title" value="{{ old('title', $form->title) }}" class="mt-1 w-full rounded-md border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $form->slug) }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="wird-aus-titel-erzeugt">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Typ</label>
                <select name="form_type" class="mt-1 w-full rounded-md border-gray-300">
                    @foreach($formTypes as $key => $label)
                        <option value="{{ $key }}" @selected(old('form_type', $form->form_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Event (optional)</label>
                <select name="event_id" class="mt-1 w-full rounded-md border-gray-300">
                    <option value="">Kein Event verknuepfen</option>
                    @foreach($events as $event)
                        <option value="{{ $event->id }}" @selected((string) old('event_id', $form->event_id) === (string) $event->id)>
                            {{ $event->title }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-md border-gray-300">{{ old('description', $form->description) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Erfolgsmeldung</label>
            <textarea name="success_message" rows="2" class="mt-1 w-full rounded-md border-gray-300">{{ old('success_message', $form->success_message) }}</textarea>
        </div>

        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5 space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-base font-semibold text-slate-900">Bestätigungsmail nach dem Absenden</div>
                    <p class="mt-1 text-sm text-slate-600">
                        Clubano verschickt direkt nach erfolgreichem Absenden eine Mail an die angegebene E-Mail-Adresse im Formular.
                    </p>
                </div>

                <label class="inline-flex items-center text-sm font-medium text-slate-700">
                    <input type="checkbox"
                           name="confirmation_mail_enabled"
                           value="1"
                           x-model="confirmationMailEnabled"
                           class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                           @checked(old('confirmation_mail_enabled', $form->confirmation_mail_enabled))>
                    <span class="ml-2">Bestätigungsmail aktivieren</span>
                </label>
            </div>

            <div x-show="confirmationMailEnabled" x-cloak class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mail-Betreff</label>
                    <input type="text"
                           name="confirmation_mail_subject"
                           value="{{ old('confirmation_mail_subject', $form->confirmation_mail_subject) }}"
                           class="mt-1 w-full rounded-md border-gray-300"
                           placeholder="z. B. Deine Anmeldung bei {verein}">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Mail-Inhalt</label>
                    @include('forms.partials.confirmation-placeholders', ['targetId' => 'confirmation_mail_body'])
                    <textarea name="confirmation_mail_body"
                              id="confirmation_mail_body"
                              rows="10"
                              class="mt-3 w-full rounded-md border-gray-300">{{ old('confirmation_mail_body', $form->confirmation_mail_body) }}</textarea>
                    <p class="mt-2 text-xs text-slate-500">
                        Der Inhalt darf formatiert sein. Du kannst neben den Standard-Platzhaltern auch eigene Feld-Slugs direkt verwenden.
                    </p>
                </div>
            </div>
        </div>

        <label class="inline-flex items-center text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $form->is_active))>
            <span class="ml-2">Formular ist aktiv und oeffentlich erreichbar</span>
        </label>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('forms.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Zurueck</a>
            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 sm:w-auto">
                {{ $submitLabel }}
            </button>
        </div>
    </form>
</div>
