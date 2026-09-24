<?php

namespace App\Http\Controllers;

use App\Mail\AutomatedClubMail;
use App\Models\Contact;
use App\Models\DataUpdateRequest;
use App\Models\Member;
use App\Models\TemplateDispatchLog;
use App\Services\TenantMailConfigurator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class DataUpdateRequestController extends Controller
{
    public function __construct(private readonly TenantMailConfigurator $tenantMailConfigurator)
    {
    }

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        return view('data-update-requests.index', [
            'members' => Member::query()
                ->where('tenant_id', $tenantId)
                ->notArchived()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'organization', 'email', 'street', 'zip', 'city']),
            'contacts' => Contact::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('organization')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'organization', 'first_name', 'last_name', 'email', 'secondary_email', 'street', 'zip', 'city']),
            'requests' => DataUpdateRequest::query()
                ->where('tenant_id', $tenantId)
                ->with(['member', 'contact', 'reviewer'])
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:member,contact'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer'],
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer'],
            'message' => ['nullable', 'string', 'max:1200'],
        ]);

        $user = $request->user();
        $tenant = $user->tenant;
        $targetIds = $data['target_type'] === 'member'
            ? ($data['member_ids'] ?? [])
            : ($data['contact_ids'] ?? []);

        if ($targetIds === []) {
            return back()
                ->withErrors(['target_ids' => 'Bitte mindestens einen Datensatz auswählen.'])
                ->withInput();
        }

        $targets = $this->targetsFor($data['target_type'], $targetIds, $user->tenant_id);
        $sent = 0;
        $skipped = 0;

        foreach ($targets as $target) {
            $email = $this->emailFor($target);

            if (blank($email)) {
                $skipped++;
                continue;
            }

            $updateRequest = DataUpdateRequest::create([
                'tenant_id' => $user->tenant_id,
                'member_id' => $target instanceof Member ? $target->id : null,
                'contact_id' => $target instanceof Contact ? $target->id : null,
                'created_by' => $user->id,
                'recipient_email' => $email,
                'recipient_name' => $this->nameFor($target),
                'token' => Str::random(48),
                'status' => DataUpdateRequest::STATUS_SENT,
                'current_data' => $this->dataFor($target),
                'message' => $data['message'] ?? null,
                'sent_at' => now(),
            ]);

            $this->sendRequestMail($updateRequest);
            $sent++;
        }

        $message = $sent . ' Anfrage(n) zur Stammdatenprüfung versendet.';

        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' Datensatz/Datensätze ohne E-Mail wurden übersprungen.';
        }

        return redirect()->route('data-update-requests.index')->with('success', $message);
    }

    public function publicShow(string $token)
    {
        $updateRequest = $this->publicRequest($token);
        $recipient = $updateRequest->recipient();

        abort_if(! $recipient, 404);

        return view('data-update-requests.public', [
            'updateRequest' => $updateRequest,
            'tenant' => $updateRequest->tenant,
            'fields' => $this->fieldsFor($updateRequest),
            'data' => array_replace($updateRequest->current_data ?? [], $this->dataFor($recipient)),
            'submitUrl' => URL::temporarySignedRoute(
                'data-update-requests.public.submit',
                now()->addDays(30),
                ['token' => $updateRequest->token],
            ),
        ]);
    }

    public function publicSubmit(Request $request, string $token)
    {
        $updateRequest = $this->publicRequest($token);
        $recipient = $updateRequest->recipient();

        abort_if(! $recipient, 404);

        $fields = $this->fieldsFor($updateRequest);
        $rules = collect($fields)->mapWithKeys(fn (array $field, string $name) => [
            $name => $field['type'] === 'email'
                ? ['nullable', 'email', 'max:255']
                : ['nullable', 'string', 'max:255'],
        ])->all();
        $rules['change_note'] = ['nullable', 'string', 'max:1200'];

        $submitted = $request->validate($rules);
        $note = $submitted['change_note'] ?? null;
        unset($submitted['change_note']);

        $current = $this->dataFor($recipient);
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

        $updateRequest->update([
            'status' => DataUpdateRequest::STATUS_SUBMITTED,
            'current_data' => $current,
            'submitted_data' => $submitted,
            'changes' => $changes,
            'message' => $note ?: $updateRequest->message,
            'submitted_at' => now(),
        ]);

        return view('data-update-requests.thank-you', [
            'tenant' => $updateRequest->tenant,
            'changesCount' => count($changes),
        ]);
    }

    public function approve(Request $request, DataUpdateRequest $dataUpdateRequest)
    {
        $this->ensureTenant($request, $dataUpdateRequest);

        abort_unless($dataUpdateRequest->status === DataUpdateRequest::STATUS_SUBMITTED, 422);

        $recipient = $dataUpdateRequest->recipient();
        abort_if(! $recipient, 404);

        $updates = collect($dataUpdateRequest->changes ?? [])
            ->mapWithKeys(fn (array $change, string $field) => [$field => $change['new'] ?? null])
            ->all();

        if ($updates !== []) {
            $recipient->fill($updates)->save();
        }

        $dataUpdateRequest->update([
            'status' => DataUpdateRequest::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('data-update-requests.index')->with('success', 'Änderungen wurden übernommen.');
    }

    public function reject(Request $request, DataUpdateRequest $dataUpdateRequest)
    {
        $this->ensureTenant($request, $dataUpdateRequest);

        abort_unless($dataUpdateRequest->status === DataUpdateRequest::STATUS_SUBMITTED, 422);

        $dataUpdateRequest->update([
            'status' => DataUpdateRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('data-update-requests.index')->with('success', 'Änderungen wurden abgelehnt.');
    }

    private function targetsFor(string $type, array $ids, int $tenantId)
    {
        $model = $type === 'member' ? Member::class : Contact::class;

        return $model::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->get();
    }

    private function publicRequest(string $token): DataUpdateRequest
    {
        return DataUpdateRequest::query()
            ->with(['tenant', 'member', 'contact'])
            ->where('token', $token)
            ->whereIn('status', [DataUpdateRequest::STATUS_SENT, DataUpdateRequest::STATUS_SUBMITTED])
            ->firstOrFail();
    }

    private function ensureTenant(Request $request, DataUpdateRequest $dataUpdateRequest): void
    {
        abort_unless((int) $dataUpdateRequest->tenant_id === (int) $request->user()->tenant_id, 404);
    }

    private function sendRequestMail(DataUpdateRequest $updateRequest): void
    {
        $tenant = $updateRequest->tenant;
        $subject = 'Bitte prüfen Sie Ihre gespeicherten Stammdaten';
        $url = URL::temporarySignedRoute(
            'data-update-requests.public.show',
            now()->addDays(30),
            ['token' => $updateRequest->token],
        );

        $body = view('mail.data-update-request', [
            'tenant' => $tenant,
            'recipientName' => $updateRequest->recipient_name,
            'message' => $updateRequest->message,
            'url' => $url,
        ])->render();

        $this->tenantMailConfigurator->apply($tenant);

        Mail::to($updateRequest->recipient_email, $updateRequest->recipient_name)
            ->send(new AutomatedClubMail(
                $tenant,
                $subject,
                $body,
                $tenant->mail_from_address ?: config('mail.from.address', 'noreply@clubano.de'),
                $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name', 'Clubano')),
                $tenant->email,
            ));

        TemplateDispatchLog::create([
            'tenant_id' => $updateRequest->tenant_id,
            'created_by' => $updateRequest->created_by,
            'channel' => 'mail',
            'action' => 'data_update_request_sent',
            'recipient_type' => $updateRequest->recipientType(),
            'member_id' => $updateRequest->member_id,
            'contact_id' => $updateRequest->contact_id,
            'recipient_name' => $updateRequest->recipient_name,
            'recipient_reference' => $updateRequest->recipient_email,
            'subject' => $subject,
            'message_excerpt' => 'Stammdatenprüfung versendet',
            'dispatched_at' => now(),
            'meta' => ['data_update_request_id' => $updateRequest->id],
        ]);
    }

    private function emailFor(Member|Contact $target): ?string
    {
        return $target instanceof Contact
            ? ($target->email ?: $target->secondary_email)
            : $target->email;
    }

    private function nameFor(Member|Contact $target): string
    {
        return $target instanceof Contact
            ? $target->display_name
            : ($target->full_name ?: $target->organization ?: $target->email);
    }

    private function fieldsFor(DataUpdateRequest $updateRequest): array
    {
        if ($updateRequest->member_id) {
            return [
                'salutation' => ['label' => 'Anrede', 'type' => 'text'],
                'title' => ['label' => 'Titel', 'type' => 'text'],
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

        return [
            'organization' => ['label' => 'Organisation/Firma', 'type' => 'text'],
            'department' => ['label' => 'Abteilung', 'type' => 'text'],
            'position' => ['label' => 'Position', 'type' => 'text'],
            'salutation' => ['label' => 'Anrede', 'type' => 'text'],
            'title' => ['label' => 'Titel', 'type' => 'text'],
            'first_name' => ['label' => 'Vorname', 'type' => 'text'],
            'last_name' => ['label' => 'Nachname', 'type' => 'text'],
            'email' => ['label' => 'E-Mail', 'type' => 'email'],
            'secondary_email' => ['label' => 'Weitere E-Mail', 'type' => 'email'],
            'mobile' => ['label' => 'Mobiltelefon', 'type' => 'text'],
            'phone' => ['label' => 'Telefon', 'type' => 'text'],
            'street' => ['label' => 'Straße und Hausnummer', 'type' => 'text'],
            'address_addition' => ['label' => 'Adresszusatz', 'type' => 'text'],
            'zip' => ['label' => 'PLZ', 'type' => 'text'],
            'city' => ['label' => 'Ort', 'type' => 'text'],
            'country' => ['label' => 'Land', 'type' => 'text'],
        ];
    }

    private function dataFor(Member|Contact $target): array
    {
        $probe = new DataUpdateRequest([
            'member_id' => $target instanceof Member ? $target->id : null,
            'contact_id' => $target instanceof Contact ? $target->id : null,
        ]);

        return collect($this->fieldsFor($probe))
            ->mapWithKeys(fn (array $field, string $name) => [$name => (string) ($target->{$name} ?? '')])
            ->all();
    }
}
