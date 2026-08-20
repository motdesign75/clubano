@extends('layouts.app')

@section('title', 'Neues Mitglied anlegen')

@section('content')
<div class="max-w-5xl mx-auto space-y-10 text-gray-800">
    <h1 class="text-3xl font-extrabold text-indigo-600">➕ Neues Mitglied</h1>

    {{-- Globale Fehlermeldungen --}}
    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <strong>Es sind Fehler aufgetreten:</strong>
            <ul class="list-disc pl-5 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('members.store') }}" method="POST" enctype="multipart/form-data"
          x-data="memberPaymentForm({
              paymentMethod: @js(old('payment_method')),
              iban: @js(old('iban')),
              bic: @js(old('bic')),
              lookupUrl: @js(route('members.bic-lookup'))
          })"
          class="bg-white shadow-2xl ring-1 ring-gray-200 rounded-2xl p-8 space-y-10">
        @csrf

        {{-- Block: Mitglied --}}
        <x-ui.formblock icon="🧍" title="Mitglied">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.select name="gender" label="Geschlecht" :options="['weiblich'=>'weiblich','männlich'=>'männlich','divers'=>'divers']" :selected="old('gender')" required />
                <x-ui.select name="salutation" label="Anrede" :options="['Frau'=>'Frau','Herr'=>'Herr','Liebe'=>'Liebe','Lieber'=>'Lieber','Hallo'=>'Hallo']" :selected="old('salutation')" required />
                <x-ui.input name="title" label="Titel" :value="old('title')" />
                <x-ui.input name="first_name" label="Vorname" :value="old('first_name')" required />
                <x-ui.input name="last_name" label="Nachname" :value="old('last_name')" required />
                <x-ui.input name="organization" label="Firma / Organisation" :value="old('organization')" />
                <x-ui.input type="date" name="birthday" label="Geburtstag" :value="old('birthday')" />

                <div>
                    <x-ui.label for="photo">Profilfoto <span class="sr-only">(optional)</span></x-ui.label>
                    <input type="file" name="photo" id="photo" accept="image/*" class="w-full file:border file:bg-indigo-100 file:text-indigo-800 file:rounded-lg file:px-4 file:py-2">
                </div>
            </div>
        </x-ui.formblock>

        {{-- Block: Mitgliedschaft --}}
        <x-ui.formblock icon="📝" title="Mitgliedschaft">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.select name="membership_id" label="Mitgliedschaft" :options="$memberships->pluck('name', 'id')" :selected="old('membership_id')" />
                <x-ui.input name="member_id" label="Mitgliedsnummer" :value="old('member_id')" />
                <x-ui.input type="date" name="entry_date" label="Eintritt" :value="old('entry_date', now()->toDateString())" required />
                <x-ui.input type="date" name="exit_date" label="Austritt" :value="old('exit_date')" />
                <x-ui.input type="date" name="termination_date" label="Kündigungsdatum" :value="old('termination_date')" />
                <div class="md:col-span-2">
                    <x-ui.label for="family_payer_id">Abrechnungszahler <span class="text-slate-400">(optional)</span></x-ui.label>
                    <select name="family_payer_id" id="family_payer_id" class="w-full rounded-2xl border-gray-300">
                        <option value="">Dieses Mitglied selbst abrechnen</option>
                        @foreach(($familyPayerCandidates ?? collect()) as $payer)
                            <option value="{{ $payer->id }}" @selected((string) old('family_payer_id') === (string) $payer->id)>
                                {{ $payer->full_name ?: ($payer->organization ?: 'Mitglied #' . $payer->id) }}{{ $payer->member_id ? ' · ' . $payer->member_id : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-sm text-slate-500">Für Familienmitgliedschaften: Dieses Mitglied wird dann nicht einzeln abgerechnet.</p>
                </div>
            </div>
        </x-ui.formblock>

        {{-- Block: Zahlung --}}
        <x-ui.formblock icon="💳" title="Zahlung">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <x-ui.label for="payment_method">Zahlungsart</x-ui.label>
                    <select name="payment_method" id="payment_method" x-model="paymentMethod" class="w-full rounded border-gray-300">
                        <option value="">Bitte wählen</option>
                        <option value="ueberweisung" @selected(old('payment_method') === 'ueberweisung')>Überweisung</option>
                        <option value="bar" @selected(old('payment_method') === 'bar')>Bar</option>
                        <option value="sepa_lastschrift" @selected(old('payment_method') === 'sepa_lastschrift')>SEPA-Lastschrift</option>
                    </select>
                </div>
            </div>

            <div x-show="paymentMethod === 'sepa_lastschrift'" x-cloak class="mt-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-ui.input name="iban" label="IBAN" :value="old('iban')" x-model="iban" @blur="lookupBic()" @change="lookupBic()" />
                        <p class="mt-2 text-xs text-slate-500">Bei deutschen IBANs ergänzen wir die BIC wenn möglich automatisch.</p>
                    </div>
                    <div>
                        <x-ui.input name="bic" label="BIC" :value="old('bic')" x-model="bic" @input="bicAutoResolved = false; bicHint = ''" />
                        <p x-show="bicHint" x-text="bicHint" class="mt-2 text-xs text-slate-500"></p>
                    </div>
                    <x-ui.input name="sepa_mandate_reference" label="Mandatsreferenz" :value="old('sepa_mandate_reference')" />
                    <x-ui.input type="date" name="sepa_signed_at" label="Unterschrieben am" :value="old('sepa_signed_at')" />
                    <x-ui.input name="sepa_account_holder" label="Abweichender Kontoinhaber" :value="old('sepa_account_holder')" />
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-4 text-sm font-medium text-slate-700">Abweichende Kontoinhaberadresse</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-ui.input name="sepa_account_holder_street" label="Straße + Nr." :value="old('sepa_account_holder_street')" />
                        <x-ui.input name="sepa_account_holder_zip" label="PLZ" :value="old('sepa_account_holder_zip')" />
                        <x-ui.input name="sepa_account_holder_city" label="Ort" :value="old('sepa_account_holder_city')" />
                        <div>
                            <x-ui.label for="sepa_account_holder_country">Land</x-ui.label>
                            <select name="sepa_account_holder_country" id="sepa_account_holder_country" class="w-full rounded border-gray-300">
                                @foreach (config('countries.list') as $code => $name)
                                    <option value="{{ $code }}" {{ old('sepa_account_holder_country', 'DE') === $code ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.formblock>

        {{-- Block: Tags --}}
        @if($allTags->isNotEmpty())
        <x-ui.formblock icon="🏷️" title="Zugewiesene Tags">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($allTags as $tag)
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                               {{ in_array($tag->id, old('tags', [])) ? 'checked' : '' }}
                               class="rounded text-indigo-600 shadow-sm border-gray-300">
                        <span>{{ $tag->name }}</span>
                    </label>
                @endforeach
            </div>
        </x-ui.formblock>
        @endif

        {{-- Block: Kommunikation --}}
        <x-ui.formblock icon="📞" title="Kommunikation">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input type="email" name="email" label="E-Mail" :value="old('email')" />
                <x-ui.input name="mobile" label="Mobilfunknummer" :value="old('mobile')" />
                <x-ui.input name="whatsapp_phone" label="WhatsApp-Nummer" :value="old('whatsapp_phone')" />
                <x-ui.input name="landline" label="Festnetznummer" :value="old('landline')" />

                <div>
                    <x-ui.label for="preferred_contact_channel">Bevorzugter Kanal</x-ui.label>
                    <select name="preferred_contact_channel" id="preferred_contact_channel" class="w-full rounded border-gray-300">
                        <option value="">Bitte wählen</option>
                        <option value="email" @selected(old('preferred_contact_channel') === 'email')>E-Mail</option>
                        <option value="phone" @selected(old('preferred_contact_channel') === 'phone')>Telefon</option>
                        <option value="whatsapp" @selected(old('preferred_contact_channel') === 'whatsapp')>WhatsApp</option>
                        <option value="post" @selected(old('preferred_contact_channel') === 'post')>Post</option>
                    </select>
                </div>

                <div>
                    <x-ui.label for="consent_given_at">Einwilligung erteilt am</x-ui.label>
                    <input type="date" name="consent_given_at" id="consent_given_at" value="{{ old('consent_given_at') }}" class="w-full rounded border-gray-300">
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-2">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="consent_email" value="1" class="rounded border-gray-300" @checked(old('consent_email'))>
                    <span>E-Mail erlaubt</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="consent_phone" value="1" class="rounded border-gray-300" @checked(old('consent_phone'))>
                    <span>Telefon erlaubt</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="consent_whatsapp" value="1" class="rounded border-gray-300" @checked(old('consent_whatsapp'))>
                    <span>WhatsApp erlaubt</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="consent_post" value="1" class="rounded border-gray-300" @checked(old('consent_post'))>
                    <span>Post erlaubt</span>
                </label>
            </div>
        </x-ui.formblock>

        <x-ui.formblock icon="🛡️" title="Datenschutz & Freigaben">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="consent_data_processing" value="1" class="rounded border-gray-300" @checked(old('consent_data_processing'))>
                    <span>Datenschutz-/Datenverarbeitungsfreigabe liegt vor</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="consent_photo_internal" value="1" class="rounded border-gray-300" @checked(old('consent_photo_internal'))>
                    <span>Foto intern im Verein verwendbar</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="consent_photo_public" value="1" class="rounded border-gray-300" @checked(old('consent_photo_public'))>
                    <span>Foto öffentlich verwendbar</span>
                </label>
                <div>
                    <x-ui.label for="deletion_requested_at">Löschvormerkung ab</x-ui.label>
                    <input type="date" name="deletion_requested_at" id="deletion_requested_at" value="{{ old('deletion_requested_at') }}" class="w-full rounded border-gray-300">
                </div>
                <div class="md:col-span-2">
                    <x-ui.label for="deletion_note">Löschhinweis / Datenschutznotiz</x-ui.label>
                    <textarea name="deletion_note" id="deletion_note" rows="3" class="w-full rounded border-gray-300">{{ old('deletion_note') }}</textarea>
                </div>
            </div>
        </x-ui.formblock>

        {{-- Block: Adresse --}}
        <x-ui.formblock icon="📍" title="Adresse">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input name="street" label="Straße + Nr." :value="old('street')" />
                <x-ui.input name="address_addition" label="Adresszusatz" :value="old('address_addition')" />
                <x-ui.input name="zip" label="PLZ" :value="old('zip')" />
                <x-ui.input name="city" label="Ort" :value="old('city')" />

                <div>
                    <x-ui.label for="country">Land</x-ui.label>
                    <select name="country" id="country" class="w-full rounded border-gray-300">
                        @foreach (config('countries.list') as $code => $name)
                            <option value="{{ $code }}" {{ old('country', 'DE') === $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <x-ui.input name="care_of" label="C/O" :value="old('care_of')" />
            </div>
        </x-ui.formblock>

        <div class="text-right">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-8 py-3 rounded-xl shadow-md transition duration-200">
                💾 Mitglied speichern
            </button>
        </div>
    </form>
</div>
@endsection

@include('members.partials.payment-form-script')
