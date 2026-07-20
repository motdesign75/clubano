<?php

namespace App\Http\Controllers;

use App\Mail\ProtocolMail;
use App\Models\Contact;
use App\Models\Member;
use App\Models\Protocol;
use App\Models\TemplateDispatchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtocolController extends Controller
{
    private function attachmentDiskForPath(string $path): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    private function normalizeTimeInput(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        return $value;
    }

    public function index()
    {
        $protocols = Protocol::where('tenant_id', auth()->user()->tenant_id)
            ->whereNull('archived_at')
            ->with('user')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $dispatchLogs = TemplateDispatchLog::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('action', 'protocol_sent')
            ->whereIn('channel', ['mail'])
            ->get()
            ->filter(fn (TemplateDispatchLog $log) => filled(data_get($log->meta, 'protocol_id')))
            ->groupBy(fn (TemplateDispatchLog $log) => (int) data_get($log->meta, 'protocol_id'));

        $protocols->transform(function (Protocol $protocol) use ($dispatchLogs) {
            $logs = $dispatchLogs->get($protocol->id, collect())->sortByDesc('dispatched_at')->values();

            $protocol->dispatch_count = $logs->count();
            $protocol->last_dispatched_at = optional($logs->first())->dispatched_at;
            $protocol->last_recipient_name = optional($logs->first())->recipient_name;
            $protocol->last_recipient_reference = optional($logs->first())->recipient_reference;

            return $protocol;
        });

        return view('protocols.index', compact('protocols'));
    }

    public function create()
    {
        $members = Member::forCurrentTenant()
            ->orderBy('last_name')
            ->get();

        return view('protocols.create', compact('members'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $request->merge([
            'start_time' => $this->normalizeTimeInput($request->input('start_time')),
            'end_time' => $this->normalizeTimeInput($request->input('end_time')),
        ]);

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'type'              => 'required|string|max:255',
            'location'          => 'nullable|string|max:255',
            'start_time'        => 'nullable|date_format:H:i',
            'end_time'          => 'nullable|date_format:H:i',
            'content'           => 'required|string',
            'resolutions'       => 'nullable|string',
            'next_meeting'      => 'nullable|string',
            'participant_ids'   => 'nullable|array',
            'participant_ids.*' => [
                'integer',
                'exists:members,id',
            ],
            'attachments'       => 'nullable|array',
            'attachments.*'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ]);

        $attachmentPaths = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file && $file->isValid()) {
                    $attachmentPaths[] = $file->store(
                        'protocols/' . $tenantId . '/attachments',
                        'local'
                    );
                }
            }
        }

        $protocolData = [
            'tenant_id'     => $tenantId,
            'user_id'       => Auth::id(),
            'title'         => $validated['title'],
            'type'          => $validated['type'],
            'location'      => $validated['location'] ?? null,
            'start_time'    => $validated['start_time'] ?? null,
            'end_time'      => $validated['end_time'] ?? null,
            'content'       => $validated['content'],
            'resolutions'   => $validated['resolutions'] ?? null,
            'next_meeting'  => $validated['next_meeting'] ?? null,
        ];

        if (Schema::hasColumn('protocols', 'attachments')) {
            $protocolData['attachments'] = $attachmentPaths;
        } elseif (Schema::hasColumn('protocols', 'attachment_paths')) {
            $protocolData['attachment_paths'] = $attachmentPaths;
        } elseif (Schema::hasColumn('protocols', 'attachment_path')) {
            $protocolData['attachment_path'] = $attachmentPaths[0] ?? null;
        }

        $protocol = Protocol::create($protocolData);

        $participantIds = collect($validated['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedParticipantIds = Member::forCurrentTenant()
            ->whereIn('id', $participantIds)
            ->pluck('id')
            ->all();

        $protocol->participants()->sync($allowedParticipantIds);

        return redirect()
            ->route('protocols.index')
            ->with('success', 'Protokoll erfolgreich gespeichert.');
    }

    public function show(Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $dispatchLogs = TemplateDispatchLog::query()
            ->where('tenant_id', $protocol->tenant_id)
            ->where('channel', 'mail')
            ->where('action', 'protocol_sent')
            ->where('meta->protocol_id', $protocol->id)
            ->with('creator')
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id')
            ->get();

        return view('protocols.show', compact('protocol', 'dispatchLogs'));
    }

    public function edit(Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $members = Member::forCurrentTenant()
            ->orderBy('last_name')
            ->get();

        $selected = $protocol->participants->pluck('id')->toArray();

        return view('protocols.edit', compact('protocol', 'members', 'selected'));
    }

    public function update(Request $request, Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $tenantId = auth()->user()->tenant_id;

        $request->merge([
            'start_time' => $this->normalizeTimeInput($request->input('start_time')),
            'end_time' => $this->normalizeTimeInput($request->input('end_time')),
        ]);

        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'type'              => 'required|string|max:255',
            'location'          => 'nullable|string|max:255',
            'start_time'        => 'nullable|date_format:H:i',
            'end_time'          => 'nullable|date_format:H:i',
            'content'           => 'required|string',
            'resolutions'       => 'nullable|string',
            'next_meeting'      => 'nullable|string',
            'participant_ids'   => 'nullable|array',
            'participant_ids.*' => [
                'integer',
                'exists:members,id',
            ],
            'attachments'       => 'nullable|array',
            'attachments.*'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ]);

        $attachmentPaths = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file && $file->isValid()) {
                    $attachmentPaths[] = $file->store(
                        'protocols/' . $tenantId . '/attachments',
                        'local'
                    );
                }
            }
        }

        $protocolData = [
            'title'         => $validated['title'],
            'type'          => $validated['type'],
            'location'      => $validated['location'] ?? null,
            'start_time'    => $validated['start_time'] ?? null,
            'end_time'      => $validated['end_time'] ?? null,
            'content'       => $validated['content'],
            'resolutions'   => $validated['resolutions'] ?? null,
            'next_meeting'  => $validated['next_meeting'] ?? null,
        ];

        if (!empty($attachmentPaths)) {
            if (Schema::hasColumn('protocols', 'attachments')) {
                $existing = is_array($protocol->attachments ?? null) ? $protocol->attachments : [];
                $protocolData['attachments'] = array_values(array_merge($existing, $attachmentPaths));
            } elseif (Schema::hasColumn('protocols', 'attachment_paths')) {
                $existing = is_array($protocol->attachment_paths ?? null) ? $protocol->attachment_paths : [];
                $protocolData['attachment_paths'] = array_values(array_merge($existing, $attachmentPaths));
            } elseif (Schema::hasColumn('protocols', 'attachment_path')) {
                $protocolData['attachment_path'] = $attachmentPaths[0];
            }
        }

        $protocol->update($protocolData);

        $participantIds = collect($validated['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedParticipantIds = Member::forCurrentTenant()
            ->whereIn('id', $participantIds)
            ->pluck('id')
            ->all();

        $protocol->participants()->sync($allowedParticipantIds);

        return redirect()
            ->route('protocols.show', $protocol)
            ->with('success', 'Protokoll erfolgreich aktualisiert.');
    }

    public function archive(Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $protocol->update([
            'archived_at' => now(),
        ]);

        return redirect()
            ->route('protocols.index')
            ->with('success', 'Protokoll wurde archiviert.');
    }

    public function destroy(Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $attachments = $protocol->attachments ?? $protocol->attachment_paths ?? [];

        if (is_string($attachments)) {
            $attachments = [$attachments];
        }

        foreach ($attachments as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $disk = $this->attachmentDiskForPath($path);

            if ($disk) {
                Storage::disk($disk)->delete($path);
            }
        }

        $protocol->delete();

        return redirect()
            ->route('protocols.index')
            ->with('success', 'Protokoll wurde geloescht.');
    }

    public function sendEmail(Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        return redirect()->route('protocols.mail.form', $protocol);
    }

    public function mailForm(Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $members = Member::where('tenant_id', $protocol->tenant_id)
            ->whereNotNull('email')
            ->orderBy('last_name')
            ->get();

        $contacts = Contact::where('tenant_id', $protocol->tenant_id)
            ->where(function ($query) {
                $query->whereNotNull('email')
                    ->orWhereNotNull('secondary_email');
            })
            ->orderBy('last_name')
            ->orderBy('organization')
            ->get();

        return view('protocols.send', compact('protocol', 'members', 'contacts'));
    }

    public function sendMail(Request $request, Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'members' => 'nullable|array',
            'members.*' => [
                'integer',
                'exists:members,id',
            ],
            'contacts' => 'nullable|array',
            'contacts.*' => [
                'integer',
                'exists:contacts,id',
            ],
            'direct_emails' => 'nullable|string',
        ]);

        $directEmails = collect(preg_split('/[\r\n,;]+/', (string) ($validated['direct_emails'] ?? '')) ?: [])
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values();

        $invalidDirectEmails = $directEmails
            ->filter(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values();

        if ($invalidDirectEmails->isNotEmpty()) {
            return back()
                ->withErrors([
                    'direct_emails' => 'Diese E-Mail-Adressen sind ungueltig: ' . $invalidDirectEmails->implode(', '),
                ])
                ->withInput();
        }

        $members = Member::where('tenant_id', $protocol->tenant_id)
            ->whereIn('id', $validated['members'] ?? [])
            ->whereNotNull('email')
            ->orderBy('last_name')
            ->get();

        $contacts = Contact::where('tenant_id', $protocol->tenant_id)
            ->whereIn('id', $validated['contacts'] ?? [])
            ->orderBy('last_name')
            ->orderBy('organization')
            ->get();

        if ($members->isEmpty() && $contacts->isEmpty() && $directEmails->isEmpty()) {
            return back()
                ->withErrors([
                    'members' => 'Bitte waehle mindestens ein Mitglied, einen Kontakt oder eine freie E-Mail-Adresse aus.',
                ])
                ->withInput();
        }

        $sentCount = 0;

        foreach ($members as $member) {
            if (! $member->email) {
                continue;
            }

            Mail::to($member->email, $member->full_name)->send(new ProtocolMail($protocol));

            TemplateDispatchLog::create([
                'tenant_id' => $protocol->tenant_id,
                'template_id' => null,
                'created_by' => auth()->id(),
                'channel' => 'mail',
                'action' => 'protocol_sent',
                'recipient_type' => 'member',
                'member_id' => $member->id,
                'contact_id' => null,
                'recipient_name' => $member->full_name,
                'recipient_reference' => $member->email,
                'subject' => 'Protokoll: ' . $protocol->title,
                'message_excerpt' => 'Protokoll "' . $protocol->title . '" wurde per Mail versendet.',
                'dispatched_at' => now(),
                'meta' => [
                    'protocol_id' => $protocol->id,
                    'protocol_title' => $protocol->title,
                ],
            ]);

            $sentCount++;
        }

        foreach ($contacts as $contact) {
            $email = $contact->primary_email;

            if (! $email) {
                continue;
            }

            Mail::to($email, $contact->display_name)->send(new ProtocolMail($protocol));

            TemplateDispatchLog::create([
                'tenant_id' => $protocol->tenant_id,
                'template_id' => null,
                'created_by' => auth()->id(),
                'channel' => 'mail',
                'action' => 'protocol_sent',
                'recipient_type' => 'contact',
                'member_id' => null,
                'contact_id' => $contact->id,
                'recipient_name' => $contact->display_name,
                'recipient_reference' => $email,
                'subject' => 'Protokoll: ' . $protocol->title,
                'message_excerpt' => 'Protokoll "' . $protocol->title . '" wurde per Mail versendet.',
                'dispatched_at' => now(),
                'meta' => [
                    'protocol_id' => $protocol->id,
                    'protocol_title' => $protocol->title,
                ],
            ]);

            $sentCount++;
        }

        foreach ($directEmails as $email) {
            Mail::to($email)->send(new ProtocolMail($protocol));

            TemplateDispatchLog::create([
                'tenant_id' => $protocol->tenant_id,
                'template_id' => null,
                'created_by' => auth()->id(),
                'channel' => 'mail',
                'action' => 'protocol_sent',
                'recipient_type' => 'free',
                'member_id' => null,
                'contact_id' => null,
                'recipient_name' => $email,
                'recipient_reference' => $email,
                'subject' => 'Protokoll: ' . $protocol->title,
                'message_excerpt' => 'Protokoll "' . $protocol->title . '" wurde per Mail versendet.',
                'dispatched_at' => now(),
                'meta' => [
                    'protocol_id' => $protocol->id,
                    'protocol_title' => $protocol->title,
                    'source' => 'manual_email',
                ],
            ]);

            $sentCount++;
        }

        return redirect()
            ->route('protocols.show', $protocol)
            ->with('success', $sentCount . ' Empfaenger haben das Protokoll per Mail erhalten.');
    }

    public function attachment(Protocol $protocol, int $index): StreamedResponse
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $attachments = $protocol->attachments ?? $protocol->attachment_paths ?? [];

        if (is_string($attachments)) {
            $attachments = [$attachments];
        }

        $path = $attachments[$index] ?? null;

        abort_unless(is_string($path) && $path !== '', 404);
        $disk = $this->attachmentDiskForPath($path);
        abort_unless($disk !== null, 404);

        return Storage::disk($disk)->response($path);
    }
}
