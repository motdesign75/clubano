<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Member;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Services\TemplateParser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = Template::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();

        $recentDispatches = TemplateDispatchLog::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->latest('dispatched_at')
            ->latest('id')
            ->take(5)
            ->get();

        return view('templates.index', compact('templates', 'recentDispatches'));
    }

    public function create()
    {
        return view('templates.create', [
            'typeOptions' => Template::typeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', Rule::in(array_keys(Template::typeOptions()))],
        ]);

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

    public function update(Request $request, Template $template)
    {
        $this->checkTenant($template);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', Rule::in(array_keys(Template::typeOptions()))],
        ]);

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
}
