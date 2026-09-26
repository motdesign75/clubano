<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataUpdateRequest;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\EventShiftAssignment;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MobileAppController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($validated['email']))])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Die Zugangsdaten sind ungültig.'], 422);
        }

        if (! $this->memberFor($user)) {
            return response()->json(['message' => 'Für diesen Zugang ist kein Mitglied verknüpft.'], 403);
        }

        $token = $user->createToken($validated['device_name'] ?? 'Clubano App', ['mobile'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user),
            'member' => $this->memberPayload($this->memberFor($user)),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Abgemeldet.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $member = $this->memberFor($user);

        abort_if(! $member, 403, 'Für diesen Zugang ist kein Mitglied verknüpft.');

        return response()->json([
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user),
            'member' => $this->memberPayload($member),
        ]);
    }

    public function submitProfileChange(Request $request)
    {
        $user = $request->user();
        $member = $this->memberFor($user);

        abort_if(! $member, 403, 'Für diesen Zugang ist kein Mitglied verknüpft.');

        $fields = $this->profileFields();
        $rules = collect($fields)->mapWithKeys(fn (array $field, string $name) => [
            $name => $field['type'] === 'email'
                ? ['nullable', 'email', 'max:255']
                : ['nullable', 'string', 'max:255'],
        ])->all();
        $rules['change_note'] = ['nullable', 'string', 'max:1200'];

        $submitted = $request->validate($rules);
        $note = $submitted['change_note'] ?? null;
        unset($submitted['change_note']);

        $current = $this->profileData($member);
        $changes = [];

        foreach ($fields as $name => $field) {
            $old = trim((string) ($current[$name] ?? ''));
            $new = trim((string) ($submitted[$name] ?? ''));

            if ($old !== $new) {
                $changes[$name] = [
                    'label' => $field['label'],
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        $updateRequest = DataUpdateRequest::create([
            'tenant_id' => $user->tenant_id,
            'member_id' => $member->id,
            'created_by' => $user->id,
            'recipient_email' => $member->email,
            'recipient_name' => $member->full_name ?: $member->organization ?: $user->name,
            'token' => bin2hex(random_bytes(24)),
            'status' => DataUpdateRequest::STATUS_SUBMITTED,
            'current_data' => $current,
            'submitted_data' => $submitted,
            'changes' => $changes,
            'message' => $note,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => count($changes) > 0
                ? 'Änderung wurde zur Prüfung eingereicht.'
                : 'Deine Daten wurden ohne Änderungen bestätigt.',
            'request_id' => $updateRequest->id,
            'changes_count' => count($changes),
        ], 201);
    }

    public function events(Request $request)
    {
        $user = $request->user();
        $member = $this->memberFor($user);

        abort_if(! $member, 403, 'Für diesen Zugang ist kein Mitglied verknüpft.');

        $events = Event::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('end', '>=', now()->subDay())
            ->with(['category', 'invitations' => fn ($query) => $query->where('member_id', $member->id)])
            ->orderBy('start')
            ->limit(100)
            ->get()
            ->map(fn (Event $event) => $this->eventPayload($event));

        return response()->json(['events' => $events]);
    }

    public function event(Request $request, Event $event)
    {
        $this->ensureTenant($request, $event);
        $member = $this->memberFor($request->user());

        abort_if(! $member, 403, 'Für diesen Zugang ist kein Mitglied verknüpft.');

        $event->load([
            'category',
            'invitations' => fn ($query) => $query->where('member_id', $member->id),
            'shifts.assignments.member',
        ]);

        $payload = $this->eventPayload($event);
        $payload['shifts'] = $event->mobile_shifts_enabled
            ? $event->shifts->map(fn ($shift) => $this->shiftPayload($shift))->values()
            : [];

        return response()->json(['event' => $payload]);
    }

    public function respondToEvent(Request $request, Event $event)
    {
        $this->ensureTenant($request, $event);
        $member = $this->memberFor($request->user());

        abort_if(! $member, 403, 'Für diesen Zugang ist kein Mitglied verknüpft.');

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EventInvitation::STATUS_ACCEPTED,
                EventInvitation::STATUS_DECLINED,
                EventInvitation::STATUS_MAYBE,
                EventInvitation::STATUS_EXCUSED,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $invitation = EventInvitation::updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'event_id' => $event->id,
                'member_id' => $member->id,
            ],
            [
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
                'responded_at' => now(),
                'recorded_by' => $request->user()->id,
            ],
        );

        return response()->json([
            'message' => 'Rückmeldung gespeichert.',
            'invitation' => $this->invitationPayload($invitation),
        ]);
    }

    public function shifts(Request $request)
    {
        $user = $request->user();
        $member = $this->memberFor($user);

        abort_if(! $member, 403, 'Für diesen Zugang ist kein Mitglied verknüpft.');

        $events = Event::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('mobile_shifts_enabled', true)
            ->whereHas('shifts', fn ($query) => $query->where('ends_at', '>=', now()->subDay()))
            ->with(['shifts.assignments.member'])
            ->orderBy('start')
            ->limit(50)
            ->get();

        return response()->json([
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => optional($event->start)->toIso8601String(),
                'location' => $event->location,
                'shifts' => $event->shifts->map(fn ($shift) => $this->shiftPayload($shift))->values(),
            ])->values(),
        ]);
    }

    public function documents(Request $request)
    {
        $documents = Document::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('status', Document::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->where('title', 'like', '%Satzung%')
                    ->orWhere('title', 'like', '%Beitragsordnung%');
            })
            ->orderBy('title')
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'document_date' => optional($document->document_date)->toDateString(),
                'mime_type' => $document->mime_type,
                'size' => $document->size,
            ]);

        return response()->json(['documents' => $documents]);
    }

    public function contact(Request $request)
    {
        $tenant = $request->user()->tenant;

        return response()->json([
            'contact' => [
                'club_name' => $tenant?->name,
                'email' => $tenant?->email,
                'phone' => $tenant?->phone,
                'address' => $tenant?->address,
                'zip' => $tenant?->zip,
                'city' => $tenant?->city,
            ],
        ]);
    }

    private function memberFor(User $user): ?Member
    {
        if ($user->member_id) {
            $member = Member::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereKey($user->member_id)
                ->first();

            if ($member) {
                return $member;
            }
        }

        return Member::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $user->email))])
            ->first();
    }

    private function ensureTenant(Request $request, Event $event): void
    {
        abort_unless((int) $event->tenant_id === (int) $request->user()->tenant_id, 404);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function tenantPayload(User $user): array
    {
        return [
            'id' => $user->tenant?->id,
            'name' => $user->tenant?->name,
            'slug' => $user->tenant?->slug,
        ];
    }

    private function memberPayload(Member $member): array
    {
        return [
            'id' => $member->id,
            'member_number' => $member->member_id,
            'full_name' => $member->full_name,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'organization' => $member->organization,
            'email' => $member->email,
            'mobile' => $member->mobile,
            'landline' => $member->landline,
            'street' => $member->street,
            'address_addition' => $member->address_addition,
            'zip' => $member->zip,
            'city' => $member->city,
            'country' => $member->country,
            'entry_date' => optional($member->entry_date)->toDateString(),
        ];
    }

    private function eventPayload(Event $event): array
    {
        $invitation = $event->invitations->first();

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => optional($event->start)->toIso8601String(),
            'ends_at' => optional($event->end)->toIso8601String(),
            'category' => $event->category?->name,
            'is_public' => (bool) $event->is_public,
            'response_required' => (bool) $event->response_required,
            'booking_enabled' => (bool) $event->booking_enabled,
            'price_label' => $event->price_label,
            'mobile_shifts_enabled' => (bool) $event->mobile_shifts_enabled,
            'invitation' => $invitation ? $this->invitationPayload($invitation) : null,
        ];
    }

    private function invitationPayload(EventInvitation $invitation): array
    {
        return [
            'status' => $invitation->status,
            'label' => $invitation->statusLabel(),
            'note' => $invitation->note,
            'responded_at' => optional($invitation->responded_at)->toIso8601String(),
        ];
    }

    private function shiftPayload($shift): array
    {
        return [
            'id' => $shift->id,
            'title' => $shift->title,
            'role' => $shift->role,
            'starts_at' => optional($shift->starts_at)->toIso8601String(),
            'ends_at' => optional($shift->ends_at)->toIso8601String(),
            'required_people' => (int) $shift->required_people,
            'open_slots' => $shift->open_slots,
            'coverage_status' => $shift->coverage_status,
            'notes' => $shift->notes,
            'assignments' => $shift->assignments
                ->filter(fn (EventShiftAssignment $assignment) => $assignment->status === 'confirmed')
                ->map(fn (EventShiftAssignment $assignment) => [
                    'id' => $assignment->id,
                    'name' => $assignment->display_name,
                    'is_me' => $assignment->member_id && Auth::user()?->member_id === $assignment->member_id,
                ])
                ->values(),
        ];
    }

    private function profileFields(): array
    {
        return [
            'first_name' => ['label' => 'Vorname', 'type' => 'text'],
            'last_name' => ['label' => 'Nachname', 'type' => 'text'],
            'organization' => ['label' => 'Organisation', 'type' => 'text'],
            'email' => ['label' => 'E-Mail', 'type' => 'email'],
            'mobile' => ['label' => 'Mobiltelefon', 'type' => 'text'],
            'landline' => ['label' => 'Telefon', 'type' => 'text'],
            'street' => ['label' => 'Straße und Hausnummer', 'type' => 'text'],
            'address_addition' => ['label' => 'Adresszusatz', 'type' => 'text'],
            'zip' => ['label' => 'PLZ', 'type' => 'text'],
            'city' => ['label' => 'Ort', 'type' => 'text'],
            'country' => ['label' => 'Land', 'type' => 'text'],
        ];
    }

    private function profileData(Member $member): array
    {
        return collect($this->profileFields())
            ->mapWithKeys(fn (array $field, string $name) => [$name => (string) ($member->{$name} ?? '')])
            ->all();
    }
}
