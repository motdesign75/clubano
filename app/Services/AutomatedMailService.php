<?php

namespace App\Services;

use App\Mail\AutomatedClubMail;
use App\Models\AutomatedMailDelivery;
use App\Models\AutomatedMailSetting;
use App\Models\Member;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AutomatedMailService
{
    public function __construct(
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly MailTrackingService $mailTrackingService,
        private readonly TenantMailConfigurator $tenantMailConfigurator,
    ) {
    }

    public function sendDueBirthdays(?int $tenantId = null, bool $dryRun = false): array
    {
        $now = now();
        $summary = [
            'checked' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'dry_run' => 0,
        ];

        $settings = AutomatedMailSetting::query()
            ->with('tenant')
            ->where('occasion', AutomatedMailSetting::OCCASION_BIRTHDAY)
            ->where('enabled', true)
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->get();

        foreach ($settings as $setting) {
            if (! $this->isDueNow($setting, $now)) {
                continue;
            }

            $targetDate = $now->copy()->startOfDay()->addDays($setting->days_before);
            $members = Member::withoutGlobalScopes()
                ->where('tenant_id', $setting->tenant_id)
                ->notArchived()
                ->whereNull('exit_date')
                ->whereNotNull('email')
                ->whereNotNull('birthday')
                ->whereMonth('birthday', $targetDate->month)
                ->whereDay('birthday', $targetDate->day)
                ->where(function ($query) {
                    $query->where('consent_email', true)
                        ->orWhereNull('consent_email');
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            foreach ($members as $member) {
                $summary['checked']++;

                if ($this->alreadyDelivered($setting, $member, $targetDate)) {
                    $summary['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $summary['dry_run']++;
                    continue;
                }

                try {
                    $this->sendBirthdayMail($setting, $member, $targetDate);
                    $summary['sent']++;
                } catch (Throwable $e) {
                    AutomatedMailDelivery::updateOrCreate([
                        'tenant_id' => $setting->tenant_id,
                        'occasion' => $setting->occasion,
                        'member_id' => $member->id,
                        'occasion_date' => $targetDate->toDateString(),
                    ], [
                        'tenant_id' => $setting->tenant_id,
                        'automated_mail_setting_id' => $setting->id,
                        'member_id' => $member->id,
                        'occasion' => $setting->occasion,
                        'occasion_date' => $targetDate->toDateString(),
                        'recipient_email' => $member->email,
                        'recipient_name' => $member->full_name,
                        'subject' => $setting->subject ?: 'Alles Gute zum Geburtstag',
                        'status' => AutomatedMailDelivery::STATUS_FAILED,
                        'error_message' => Str::limit($e->getMessage(), 1000),
                    ]);

                    $summary['failed']++;
                }
            }
        }

        return $summary;
    }

    public function sendTestBirthdayMail(AutomatedMailSetting $setting, string $toEmail, ?Member $exampleMember = null): void
    {
        $tenant = $setting->tenant;
        $member = $exampleMember ?: new Member([
            'tenant_id' => $tenant->id,
            'first_name' => 'Mara',
            'last_name' => 'Mustermann',
            'email' => $toEmail,
            'birthday' => now()->subYears(42)->toDateString(),
        ]);

        $birthdayDate = now()->startOfDay();
        [$subject, $body] = $this->renderBirthdayContent($setting, $member, $birthdayDate);
        $this->sendMail($tenant, $toEmail, $member->full_name ?: $toEmail, $subject, $body);
    }

    private function sendBirthdayMail(AutomatedMailSetting $setting, Member $member, Carbon $birthdayDate): void
    {
        $tenant = $setting->tenant;
        [$subject, $body] = $this->renderBirthdayContent($setting, $member, $birthdayDate);

        $dispatchLog = TemplateDispatchLog::create([
            'tenant_id' => $tenant->id,
            'template_id' => null,
            'created_by' => null,
            'channel' => 'mail',
            'action' => 'sent',
            'recipient_type' => 'member',
            'member_id' => $member->id,
            'contact_id' => null,
            'recipient_name' => $member->full_name,
            'recipient_reference' => $member->email,
            'subject' => $subject,
            'message_excerpt' => Str::limit(strip_tags($body), 240),
            'dispatched_at' => now(),
            'meta' => [
                'composition_mode' => 'automated',
                'occasion' => $setting->occasion,
            ],
        ]);

        $trackedBody = $this->mailTrackingService->instrument($body, $dispatchLog);

        try {
            $this->sendMail($tenant, $member->email, $member->full_name, $subject, $trackedBody);
        } catch (Throwable $e) {
            $dispatchLog->delete();
            throw $e;
        }

        AutomatedMailDelivery::updateOrCreate([
            'tenant_id' => $tenant->id,
            'occasion' => $setting->occasion,
            'member_id' => $member->id,
            'occasion_date' => $birthdayDate->toDateString(),
        ], [
            'tenant_id' => $tenant->id,
            'automated_mail_setting_id' => $setting->id,
            'member_id' => $member->id,
            'occasion' => $setting->occasion,
            'occasion_date' => $birthdayDate->toDateString(),
            'recipient_email' => $member->email,
            'recipient_name' => $member->full_name,
            'subject' => $subject,
            'status' => AutomatedMailDelivery::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    private function sendMail(Tenant $tenant, string $toEmail, string $toName, string $subject, string $body): void
    {
        $this->tenantMailConfigurator->apply($tenant);

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = $tenant->email && $tenant->email !== $fromAddress ? $tenant->email : null;

        Mail::to($toEmail, $toName !== $toEmail ? $toName : null)->send(
            new AutomatedClubMail($tenant, $subject, $body, $fromAddress, $fromName, $replyToAddress)
        );
    }

    private function renderBirthdayContent(AutomatedMailSetting $setting, Member $member, Carbon $birthdayDate): array
    {
        $tenant = $setting->tenant;
        $subject = $setting->subject ?: 'Alles Gute zum Geburtstag, {{ vorname }}';
        $body = $setting->body_html ?: '<p>Liebe/r {{ vorname }},</p><p>wir wünschen dir alles Gute zum Geburtstag.</p><p>Viele Grüße<br>{{ verein }}</p>';
        $age = $member->birthday ? Carbon::parse($member->birthday)->diffInYears($birthdayDate) : null;
        $replacements = [
            'name' => e($member->full_name),
            'vorname' => e($member->first_name ?: $member->full_name),
            'nachname' => e($member->last_name ?: ''),
            'verein' => e($tenant->name ?: 'dein Verein'),
            'geburtstag' => e($birthdayDate->format('d.m.Y')),
            'alter' => e($age !== null ? (string) $age : ''),
            'vorstand_unterschriften' => TemplateParser::parse('{vorstand_unterschriften}', $member, $tenant),
        ];
        $subject = $this->replacePlaceholders($this->htmlSanitizer->normalize($subject), $replacements);
        $body = $this->replacePlaceholders($body, $replacements);

        return [
            trim(strip_tags($subject)) ?: 'Alles Gute zum Geburtstag',
            $this->htmlSanitizer->sanitize($body) ?: '',
        ];
    }

    private function replacePlaceholders(string $value, array $replacements): string
    {
        foreach ($replacements as $key => $replacement) {
            $value = preg_replace('/\{\{\s*' . preg_quote((string) $key, '/') . '\s*\}\}/u', (string) $replacement, $value) ?? $value;
        }

        return $value;
    }

    private function isDueNow(AutomatedMailSetting $setting, Carbon $now): bool
    {
        if (! preg_match('/^\d{2}:\d{2}$/', (string) $setting->send_time)) {
            return true;
        }

        return $now->format('H:i') >= $setting->send_time;
    }

    private function alreadyDelivered(AutomatedMailSetting $setting, Member $member, Carbon $occasionDate): bool
    {
        return AutomatedMailDelivery::query()
            ->where('tenant_id', $setting->tenant_id)
            ->where('occasion', $setting->occasion)
            ->where('member_id', $member->id)
            ->whereDate('occasion_date', $occasionDate->toDateString())
            ->whereIn('status', [AutomatedMailDelivery::STATUS_SENT, AutomatedMailDelivery::STATUS_DRY_RUN])
            ->exists();
    }
}
