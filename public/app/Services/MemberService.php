<?php

namespace App\Services;

use App\Models\Member;
use App\Models\CustomMemberValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberService
{
    /**
     * Neues Mitglied erstellen
     */
    public function create(Request $request): void
    {
        $data = $request->validated();

        $data['tenant_id'] = app('currentTenant')->id;
        $data['country'] = $data['country'] ?? 'DE'; // fallback für Pflichtfelder

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $member = Member::create($data);

        // Benutzerdefinierte Felder speichern
        $this->syncCustomFields($member, $request->input('custom_fields', []));
    }

    /**
     * Bestehendes Mitglied aktualisieren
     */
    public function update(Request $request, Member $member): void
    {
        $data = $request->validated();

        // Wenn kein Land übermittelt wurde, alten Wert beibehalten
        $data['country'] = $data['country'] ?? $member->country;

        if ($request->hasFile('photo')) {
            if ($member->photo && Storage::disk('public')->exists($member->photo)) {
                Storage::disk('public')->delete($member->photo);
            }

            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $member->update($data);

        // Benutzerdefinierte Felder aktualisieren
        $this->syncCustomFields($member, $request->input('custom_fields', []));
    }

    /**
     * Benutzerdefinierte Felder synchronisieren
     */
    protected function syncCustomFields(Member $member, array $fields): void
    {
        foreach ($fields as $fieldId => $value) {
            CustomMemberValue::updateOrCreate(
                [
                    'member_id' => $member->id,
                    'custom_member_field_id' => $fieldId,
                ],
                [
                    'value' => $value,
                ]
            );
        }
    }
}
