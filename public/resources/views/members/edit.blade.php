@extends('layouts.app')

@section('title', 'Mitglied bearbeiten')

@section('content')
<div class="max-w-5xl mx-auto space-y-10 text-gray-800">
    <h1 class="text-3xl font-extrabold text-indigo-600">✏️ Mitglied bearbeiten</h1>

    <form action="{{ route('members.update', $member) }}" method="POST" enctype="multipart/form-data" class="bg-white shadow-2xl ring-1 ring-gray-200 rounded-2xl p-8 space-y-10">
        @csrf
        @method('PATCH')

        {{-- Block: Mitglied --}}
        <x-ui.formblock icon="🧍" title="Mitglied">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.select name="gender" label="Geschlecht" :options="['weiblich'=>'weiblich','männlich'=>'männlich','divers'=>'divers']" :selected="old('gender', $member->gender)" />
                <x-ui.select name="salutation" label="Anrede" :options="['Frau'=>'Frau','Herr'=>'Herr','Liebe'=>'Liebe','Lieber'=>'Lieber','Hallo'=>'Hallo']" :selected="old('salutation', $member->salutation)" />
                <x-ui.input name="title" label="Titel" :value="old('title', $member->title)" />
                <x-ui.input name="first_name" label="Vorname" :value="old('first_name', $member->first_name)" required />
                <x-ui.input name="last_name" label="Nachname" :value="old('last_name', $member->last_name)" required />
                <x-ui.input name="organization" label="Firma / Organisation" :value="old('organization', $member->organization)" />
                <x-ui.input type="date" name="birthday" label="Geburtstag" :value="old('birthday', $member->birthday)" />

                <div>
                    <x-ui.label for="photo">Profilfoto</x-ui.label>
                    <input type="file" name="photo" id="photo" accept="image/*" class="w-full file:border file:bg-indigo-100 file:text-indigo-800 file:rounded-lg file:px-4 file:py-2">
                    @if ($member->photo)
                        <div class="mt-4 flex items-center gap-4">
                            <img src="{{ asset('storage/' . $member->photo) }}" alt="Foto" class="w-24 h-24 rounded-full border shadow">
                            <span class="text-sm text-gray-500 italic">Aktuelles Foto</span>
                        </div>
                    @endif
                </div>
            </div>
        </x-ui.formblock>

        {{-- Block: Mitgliedschaft --}}
        <x-ui.formblock icon="📝" title="Mitgliedschaft">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-ui.label for="membership_id">Mitgliedschaft</x-ui.label>
                    <select name="membership_id" id="membership_id" class="w-full rounded border-gray-300">
                        <option value="">– bitte wählen –</option>
                        @foreach($memberships as $membership)
                            <option value="{{ $membership->id }}" {{ $member->membership_id == $membership->id ? 'selected' : '' }}>
                                {{ $membership->name }} – {{ number_format($membership->fee, 2, ',', '.') }} € / {{ $membership->billing_cycle }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <x-ui.input name="member_id" label="Mitgliedsnummer" :value="old('member_id', $member->member_id)" />
                <x-ui.input type="date" name="entry_date" label="Eintritt" :value="old('entry_date', $member->entry_date)" />
                <x-ui.input type="date" name="exit_date" label="Austritt" :value="old('exit_date', $member->exit_date)" />
                <x-ui.input type="date" name="termination_date" label="Kündigungsdatum" :value="old('termination_date', $member->termination_date)" />
            </div>
        </x-ui.formblock>

        {{-- Block: Kommunikation --}}
        <x-ui.formblock icon="📞" title="Kommunikation">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input type="email" name="email" label="E-Mail" :value="old('email', $member->email)" />
                <x-ui.input name="mobile" label="Mobilfunknummer" :value="old('mobile', $member->mobile)" />
                <x-ui.input name="landline" label="Festnetznummer" :value="old('landline', $member->landline)" />
            </div>
        </x-ui.formblock>

        {{-- Block: Adresse --}}
        <x-ui.formblock icon="📍" title="Adresse">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-ui.input name="street" label="Straße + Nr." :value="old('street', $member->street)" />
                <x-ui.input name="address_addition" label="Adresszusatz" :value="old('address_addition', $member->address_addition)" />
                <x-ui.input name="zip" label="PLZ" :value="old('zip', $member->zip)" />
                <x-ui.input name="city" label="Ort" :value="old('city', $member->city)" />
                <div>
                    <x-ui.label for="country">Land</x-ui.label>
                    <select name="country" id="country" class="w-full rounded border-gray-300">
                        @foreach (config('countries.list') as $code => $name)
                            <option value="{{ $code }}" {{ $member->country === $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.input name="care_of" label="C/O" :value="old('care_of', $member->care_of)" />
            </div>
        </x-ui.formblock>

        {{-- Block: Benutzerdefinierte Felder --}}
        @if (isset($customFields) && count($customFields))
        <x-ui.formblock icon="🧩" title="Weitere Angaben">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach ($customFields as $field)
                    @php
                        $name = 'custom_fields[' . $field->id . ']';
                        $label = $field->label;
                        $value = old("custom_fields.{$field->id}", optional($member->customValues->firstWhere('custom_member_field_id', $field->id))->value);
                    @endphp

                    @if ($field->type === 'text')
                        <x-ui.input :name="$name" :label="$label" :value="$value" />
                    @elseif ($field->type === 'email')
                        <x-ui.input type="email" :name="$name" :label="$label" :value="$value" />
                    @elseif ($field->type === 'date')
                        <x-ui.input type="date" :name="$name" :label="$label" :value="$value" />
                    @elseif ($field->type === 'number')
                        <x-ui.input type="number" :name="$name" :label="$label" :value="$value" />
                    @elseif ($field->type === 'select')
                        @php
                            $options = collect(explode(',', $field->options))->mapWithKeys(fn($v) => [trim($v) => trim($v)]);
                        @endphp
                        <x-ui.select :name="$name" :label="$label" :options="$options" :selected="$value" />
                    @endif
                @endforeach
            </div>
        </x-ui.formblock>
        @endif

        <div class="text-right">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-8 py-3 rounded-xl shadow-md transition duration-200">
                💾 Änderungen speichern
            </button>
        </div>
    </form>
</div>
@endsection
