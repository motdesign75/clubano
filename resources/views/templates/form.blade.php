<div class="space-y-4">
<div>
<label class="block font-semibold">Name</label>
<input type="text" name="name" value="{{ old('name',$template->name ?? '') }}" class="w-full border rounded p-2">
</div>
<div>
<label class="block font-semibold">Typ</label>
<select name="type" class="w-full border rounded p-2">
@foreach(($typeOptions ?? \App\Models\Template::typeOptions()) as $value => $label)
<option value="{{ $value }}" {{ old('type',$template->type ?? '') == $value ? 'selected' : '' }}>{{ $label }}</option>
@endforeach
</select>
</div>
<div>
<label class="block font-semibold">Betreff / Überschrift</label>
<input type="text" name="subject" value="{{ old('subject',$template->subject ?? '') }}" class="w-full border rounded p-2">
<p class="mt-1 text-xs text-slate-500">Für Mails wird daraus der Mail-Betreff. Für Briefe erscheint der Text als Betreffzeile.</p>
</div>
<div>
<label class="block font-semibold mb-1">Text / Inhalt</label>
@include('templates.partials.placeholders')
<textarea name="body" id="body" class="w-full border rounded p-2" rows="10">{{ old('body',$template->body ?? '') }}</textarea>
</div>
<button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Speichern</button>
</div>
