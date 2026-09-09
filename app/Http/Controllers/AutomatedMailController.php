<?php

namespace App\Http\Controllers;

use App\Models\AutomatedMailDelivery;
use App\Models\AutomatedMailSetting;
use App\Models\Member;
use App\Services\AutomatedMailService;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;

class AutomatedMailController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;
        $settings = collect(AutomatedMailSetting::occasionLabels())
            ->mapWithKeys(function (string $label, string $occasion) use ($tenant) {
                $setting = AutomatedMailSetting::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'occasion' => $occasion,
                    ],
                    [
                        'enabled' => false,
                        'subject' => $this->defaultSubject($occasion),
                        'body_html' => $this->defaultBody($occasion),
                        'days_before' => 0,
                        'send_time' => '09:00',
                    ]
                );

                return [$occasion => ['label' => $label, 'setting' => $setting]];
            });

        $birthdayCandidates = Member::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->notArchived()
            ->whereNull('exit_date')
            ->whereNotNull('email')
            ->whereNotNull('birthday')
            ->count();

        $recentDeliveries = AutomatedMailDelivery::query()
            ->where('tenant_id', $tenant->id)
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('automated-mails.index', compact('settings', 'birthdayCandidates', 'recentDeliveries'));
    }

    public function update(Request $request, string $occasion, HtmlSanitizer $htmlSanitizer)
    {
        abort_unless(array_key_exists($occasion, AutomatedMailSetting::occasionLabels()), 404);

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:20000'],
            'days_before' => ['required', 'integer', 'min:0', 'max:14'],
            'send_time' => ['required', 'date_format:H:i'],
        ]);

        AutomatedMailSetting::updateOrCreate(
            [
                'tenant_id' => auth()->user()->tenant_id,
                'occasion' => $occasion,
            ],
            [
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'subject' => $htmlSanitizer->normalize($validated['subject']),
                'body_html' => $htmlSanitizer->sanitize($validated['body_html']),
                'days_before' => (int) $validated['days_before'],
                'send_time' => $validated['send_time'],
            ]
        );

        return redirect()
            ->route('automated-mails.index')
            ->with('success', 'Automatische Mail gespeichert.');
    }

    public function test(Request $request, string $occasion, AutomatedMailService $automatedMailService)
    {
        abort_unless(array_key_exists($occasion, AutomatedMailSetting::occasionLabels()), 404);

        $validated = $request->validate([
            'test_email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $setting = AutomatedMailSetting::where('tenant_id', auth()->user()->tenant_id)
            ->where('occasion', $occasion)
            ->firstOrFail();

        $exampleMember = Member::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->notArchived()
            ->whereNotNull('birthday')
            ->orderBy('last_name')
            ->first();

        $automatedMailService->sendTestBirthdayMail($setting, $validated['test_email'], $exampleMember);

        return redirect()
            ->route('automated-mails.index')
            ->with('success', 'Testmail wurde versendet.');
    }

    private function defaultSubject(string $occasion): string
    {
        return match ($occasion) {
            AutomatedMailSetting::OCCASION_BIRTHDAY => 'Alles Gute zum Geburtstag, {{ vorname }}',
            default => 'Nachricht von {{ verein }}',
        };
    }

    private function defaultBody(string $occasion): string
    {
        return match ($occasion) {
            AutomatedMailSetting::OCCASION_BIRTHDAY => '<p>Liebe/r {{ vorname }},</p><p>wir wünschen dir alles Gute zum Geburtstag und einen schönen Tag.</p><p>Viele Grüße<br>{{ verein }}</p>',
            default => '<p>Hallo {{ vorname }},</p><p>viele Grüße<br>{{ verein }}</p>',
        };
    }
}
