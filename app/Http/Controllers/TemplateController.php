<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Member;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Services\HtmlSanitizer;
use App\Services\TemplateParser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        $search = trim((string) request('search', ''));
        $type = trim((string) request('type', ''));

        $baseQuery = Template::where('tenant_id', $tenantId);

        $templateTotalCount = (clone $baseQuery)->count();
        $mailTemplateCount = (clone $baseQuery)
            ->whereIn('type', [Template::TYPE_MAIL, Template::TYPE_MAIL_AND_LETTER])
            ->count();
        $letterTemplateCount = (clone $baseQuery)
            ->whereIn('type', [Template::TYPE_LETTER, Template::TYPE_MAIL_AND_LETTER])
            ->count();

        $templates = (clone $baseQuery)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('subject', 'like', '%' . $search . '%')
                        ->orWhere('body', 'like', '%' . $search . '%');
                });
            })
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $recentDispatches = TemplateDispatchLog::query()
            ->where('tenant_id', $tenantId)
            ->latest('dispatched_at')
            ->latest('id')
            ->take(5)
            ->get();

        return view('templates.index', [
            'templates' => $templates,
            'recentDispatches' => $recentDispatches,
            'templateTotalCount' => $templateTotalCount,
            'mailTemplateCount' => $mailTemplateCount,
            'letterTemplateCount' => $letterTemplateCount,
            'typeOptions' => Template::typeOptions(),
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function create()
    {
        return view('templates.create', [
            'typeOptions' => Template::typeOptions(),
        ]);
    }

    public function store(Request $request, HtmlSanitizer $htmlSanitizer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', Rule::in(array_keys(Template::typeOptions()))],
            'template_button_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['body'] = $this->repairTemplateButtonLink($data['body'], $data['template_button_url'] ?? null);
        $data['body'] = $htmlSanitizer->sanitize($data['body']) ?? '';
        unset($data['template_button_url']);
        $data['tenant_id'] = auth()->user()->tenant_id;

        Template::create($data);

        return redirect()
            ->route('templates.index')
            ->with('success', 'Vorlage gespeichert');
    }

    public function edit(Template $template)
    {
        $this->checkTenant($template);

        return view('templates.edit', [
            'template' => $template,
            'typeOptions' => Template::typeOptions(),
        ]);
    }

    public function update(Request $request, Template $template, HtmlSanitizer $htmlSanitizer)
    {
        $this->checkTenant($template);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', Rule::in(array_keys(Template::typeOptions()))],
            'template_button_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['body'] = $this->repairTemplateButtonLink($data['body'], $data['template_button_url'] ?? null);
        $data['body'] = $htmlSanitizer->sanitize($data['body']) ?? '';
        unset($data['template_button_url']);

        $template->update($data);

        return redirect()
            ->route('templates.index')
            ->with('success', 'Vorlage aktualisiert');
    }

    public function destroy(Template $template)
    {
        $this->checkTenant($template);

        $template->delete();

        return back()->with('success', 'Vorlage gelöscht');
    }

    public function preview(Template $template)
    {
        $this->checkTenant($template);

        $tenantId = auth()->user()->tenant_id;
        $recipient = Member::where('tenant_id', $tenantId)->first()
            ?? Contact::where('tenant_id', $tenantId)->first()
            ?? [
                'tenant_id' => $tenantId,
                'name' => 'Max Mustermann',
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'organization' => 'Musterorganisation',
                'email' => 'max@example.org',
                'phone' => '05181 123456',
                'street' => 'Musterweg 1',
                'zip' => '31157',
                'city' => 'Sarstedt',
                'country' => 'Deutschland',
                'salutation' => 'Guten Tag Max Mustermann',
            ];

        $text = TemplateParser::parse($template->body, $recipient);

        return view('templates.preview', compact('template', 'text'));
    }

    private function checkTenant(Template $template): void
    {
        if ((string) $template->tenant_id !== (string) auth()->user()->tenant_id) {
            abort(403);
        }
    }

    private function repairTemplateButtonLink(string $body, ?string $buttonUrl): string
    {
        $buttonUrl = $this->normalizeTemplateButtonUrl($buttonUrl);

        if ($buttonUrl === null || ! preg_match('/JETZT\s+ANMELDEN|Jetzt\s+öffnen|Jetzt\s+oeffnen/iu', strip_tags($body))) {
            return $body;
        }

        if (preg_match('/<a\b[^>]*\bhref\s*=\s*["\'](?!\s*["\'])[^"\']+["\'][^>]*>/iu', $body)) {
            return $body;
        }

        if (preg_match('/<a\b(?![^>]*\bhref\s*=)([^>]*)>/iu', $body)) {
            return preg_replace('/<a\b(?![^>]*\bhref\s*=)([^>]*)>/iu', '<a href="' . e($buttonUrl) . '"$1>', $body, 1) ?? $body;
        }

        return $body;
    }

    private function normalizeTemplateButtonUrl(?string $buttonUrl): ?string
    {
        $buttonUrl = trim((string) $buttonUrl);

        if ($buttonUrl === '' || $buttonUrl === '{link}') {
            return $buttonUrl ?: null;
        }

        if (preg_match('/^www\.[^\s]+$/i', $buttonUrl)) {
            return 'https://' . $buttonUrl;
        }

        if (filter_var($buttonUrl, FILTER_VALIDATE_URL)) {
            return $buttonUrl;
        }

        return null;
    }
}
