@extends('layouts.app')

@section('title', 'CSV-Vorschau & Feldzuordnung')

@section('content')
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="bg-white rounded shadow p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">📊 CSV-Vorschau & Feldzuordnung</h1>

            @if ($errors->any())
                <div class="bg-red-100 text-red-800 p-4 rounded mb-4">
                    <strong>Fehler:</strong>
                    <ul class="list-disc list-inside mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('import.mitglieder.confirm') }}">
                @csrf
                <input type="hidden" name="path" value="{{ $path }}">

                <div class="overflow-auto mb-6">
                    <table class="table-auto w-full text-sm border-collapse border">
                        <thead class="bg-gray-100">
                            <tr>
                                @foreach($headers as $i => $header)
                                    <th class="border px-3 py-2">
                                        <div class="font-bold text-xs uppercase text-gray-700">{{ $header }}</div>
                                        <div>
                                            <select name="mapping[{{ $i }}]" class="mt-1 block w-full text-xs border rounded p-1">
                                                <option value="skip">Ignorieren</option>

                                                <optgroup label="🧍 Mitglied">
                                                    <option value="gender">Geschlecht</option>
                                                    <option value="salutation">Anrede</option>
                                                    <option value="title">Titel</option>
                                                    <option value="first_name">Vorname</option>
                                                    <option value="last_name">Nachname</option>
                                                    <option value="company">Firma / Organisation</option>
                                                    <option value="birthday">Geburtstag</option>
                                                </optgroup>

                                                <optgroup label="🪪 Mitgliedschaft">
                                                    <option value="member_id">Mitgliedsnummer</option>
                                                    <option value="entry_date">Eintritt</option>
                                                    <option value="exit_date">Austritt</option>
                                                    <option value="termination_date">Kündigungsdatum</option>
                                                </optgroup>

                                                <optgroup label="📞 Kommunikation">
                                                    <option value="email">E-Mail</option>
                                                    <option value="mobile">Mobilfunknummer</option>
                                                    <option value="phone">Festnetznummer</option>
                                                </optgroup>

                                                <optgroup label="🏠 Adresse">
                                                    <option value="street">Straße + Nr.</option>
                                                    <option value="address_extra">Adresszusatz</option>
                                                    <option value="zip">PLZ</option>
                                                    <option value="city">Ort</option>
                                                    <option value="country">Land</option>
                                                    <option value="co">C/O</option>
                                                </optgroup>

                                                <optgroup label="🏷️ Tags & Foto">
                                                    <option value="tags">Tags</option>
                                                    <option value="photo">Profilfoto (Dateiname)</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    @foreach($row as $value)
                                        <td class="border px-3 py-2 text-sm text-gray-800">
                                            {{ $value }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded">
                        ✅ Import starten
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
