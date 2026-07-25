<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberCommunicationLog;
use App\Models\Membership;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MemberService
{
    public function __construct(
        private readonly GermanIbanBicResolver $ibanBicResolver
    ) {
    }

    public function create(StoreMemberRequest $request): Member
    {
        $data = $request->validated();

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['consent_email'] = $request->boolean('consent_email');
        $data['consent_phone'] = $request->boolean('consent_phone');
        $data['consent_post'] = $request->boolean('consent_post');
        $data['consent_whatsapp'] = $request->boolean('consent_whatsapp');
        $data['consent_data_processing'] = $request->boolean('consent_data_processing');
        $data['consent_photo_internal'] = $request->boolean('consent_photo_internal');
        $data['consent_photo_public'] = $request->boolean('consent_photo_public');
        $data['required_service_hours'] = $data['required_service_hours'] ?? 0;
        $data = $this->normalizePaymentData($data);

        // Foto speichern, falls vorhanden
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $data = $this->applyMembershipSnapshot($data, $data['tenant_id']);

        // Mitglied anlegen und zurückgeben
        return Member::create($data);
    }

    public function update(UpdateMemberRequest $request, Member $member): void
    {
        $originalExitDate = optional($member->exit_date)?->toDateString();

        $data = $request->validated();
        $data['consent_email'] = $request->boolean('consent_email');
        $data['consent_phone'] = $request->boolean('consent_phone');
        $data['consent_post'] = $request->boolean('consent_post');
        $data['consent_whatsapp'] = $request->boolean('consent_whatsapp');
        $data['consent_data_processing'] = $request->boolean('consent_data_processing');
        $data['consent_photo_internal'] = $request->boolean('consent_photo_internal');
        $data['consent_photo_public'] = $request->boolean('consent_photo_public');
        $data['required_service_hours'] = $data['required_service_hours'] ?? 0;
        $data = $this->normalizePaymentData($data);

        // Mandant prüfen
        if ($member->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Foto aktualisieren
        if ($request->hasFile('photo')) {
            if ($member->photo && Storage::disk('public')->exists($member->photo)) {
                Storage::disk('public')->delete($member->photo);
            }

            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $data = $this->applyMembershipSnapshot($data, $member->tenant_id);

        $member->update($data);

        $member->refresh()->loadMissing('tenant', 'membership');

        $this->sendExitConfirmationMailIfNeeded($member, $originalExitDate);
    }

    private function applyMembershipSnapshot(array $data, int|string $tenantId): array
    {
        $data['membership_amount'] = null;
        $data['membership_interval'] = null;

        if (empty($data['membership_id'])) {
            return $data;
        }

        $membership = Membership::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $data['membership_id'])
            ->first();

        if (!$membership) {
            return $data;
        }

        $data['membership_amount'] = $membership->amount;
        $data['membership_interval'] = $membership->interval;

        return $data;
    }

    private function normalizePaymentData(array $data): array
    {
        if (($data['payment_method'] ?? null) === 'sepa_lastschrift') {
            $data['iban'] = filled($data['iban'] ?? null)
                ? $this->ibanBicResolver->normalizeIban($data['iban'])
                : null;

            $data['bic'] = filled($data['bic'] ?? null)
                ? $this->ibanBicResolver->normalizeBic($data['bic'])
                : null;

            if (filled($data['iban'] ?? null) && blank($data['bic'] ?? null)) {
                $resolved = $this->ibanBicResolver->resolve($data['iban'], auth()->user()?->tenant_id);

                if ($resolved) {
                    $data['bic'] = $resolved['bic'];
                }
            }

            if (empty($data['sepa_account_holder_country'])) {
                $data['sepa_account_holder_country'] = 'DE';
            }

            return $data;
        }

        foreach ([
            'iban',
            'bic',
            'sepa_mandate_reference',
            'sepa_signed_at',
            'sepa_account_holder',
            'sepa_account_holder_street',
            'sepa_account_holder_zip',
            'sepa_account_holder_city',
            'sepa_account_holder_country',
        ] as $field) {
            $data[$field] = null;
        }

        return $data;
    }

    private function sendExitConfirmationMailIfNeeded(Member $member, ?string $originalExitDate): void
    {
        $tenant = $member->tenant;
        $newExitDate = optional($member->exit_date)?->toDateString();

        if (! $tenant?->member_exit_mail_enabled || blank($member->email) || blank($newExitDate)) {
            return;
        }

        if ($newExitDate === $originalExitDate) {
            return;
        }

        $subjectTemplate = trim((string) ($tenant->member_exit_mail_subject ?: 'Bestaetigung deines Austritts bei {verein}'));
        $bodyTemplate = trim((string) ($tenant->member_exit_mail_body ?: '<p>{anrede},</p><p>wir bestaetigen dir hiermit deinen Austritt aus <strong>{verein}</strong> zum <strong>{austrittsdatum}</strong>.</p><p>Danke fuer die gemeinsame Zeit und alles, was du eingebracht hast.</p><p>Herzliche Gruesse<br>{verein}</p>'));

        $subject = TemplateParser::parse($subjectTemplate, $member, $tenant);
        $body = TemplateParser::parse($bodyTemplate, $member, $tenant);

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = $tenant->email && $tenant->email !== $fromAddress
            ? $tenant->email
            : null;

        try {
            Mail::send('mail.layout', [
                'body' => $body,
                'tenant' => $tenant,
            ], function ($mail) use ($member, $subject, $fromAddress, $fromName, $replyToAddress, $tenant) {
                $mail->to($member->email, $member->full_name ?: null)
                    ->subject($subject)
                    ->from($fromAddress, $fromName);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?: $fromName);
                }
            });

            MemberCommunicationLog::create([
                'tenant_id' => $member->tenant_id,
                'member_id' => $member->id,
                'created_by' => auth()->id(),
                'channel' => 'email',
                'direction' => 'outgoing',
                'recipient' => $member->email,
                'subject' => $subject,
                'message' => strip_tags($body),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Austrittsmail konnte nicht gesendet werden.', [
                'member_id' => $member->id,
                'tenant_id' => $member->tenant_id,
                'email' => $member->email,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
