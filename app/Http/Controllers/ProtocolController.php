<?php

namespace App\Http\Controllers;

use App\Mail\ProtocolMail;
use App\Models\Contact;
use App\Models\Member;
use App\Models\Protocol;
use App\Models\ProtocolEntry;
use App\Models\Task;
use App\Models\TemplateDispatchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    private function validatedEntryRules(): array
    {
        $types = implode(',', array_keys(ProtocolEntry::typeOptions()));

        return [
            'entries' => 'nullable|array',
            'entries.*.agenda_title' => 'nullable|string|max:255',
            'entries.*.type' => 'nullable|in:' . $types,
            'entries.*.title' => 'nullable|string|max:255',
            'entries.*.content' => 'nullable|string',
            'entries.*.responsible_name' => 'nullable|string|max:255',
            'entries.*.due_date' => 'nullable|date',
            'entries.*.scheduled_date' => 'nullable|date',
            'entries.*.visible_in_protocol' => 'nullable|boolean',
        ];
    }

    private function normalizeProtocolEntries(array $entries): array
    {
        return collect($entries)
            ->map(function (array $entry) {
                return [
                    'type' => $entry['type'] ?? ProtocolEntry::TYPE_INFORMATION,
                    'agenda_title' => trim((string) ($entry['agenda_title'] ?? '')),
                    'title' => trim((string) ($entry['title'] ?? '')),
                    'content' => trim((string) ($entry['content'] ?? '')),
                    'responsible_name' => trim((string) ($entry['responsible_name'] ?? '')),
                    'due_date' => $entry['due_date'] ?? null,
                    'scheduled_date' => $entry['scheduled_date'] ?? null,
                    'visible_in_protocol' => (bool) ($entry['visible_in_protocol'] ?? true),
                ];
            })
            ->filter(fn (array $entry) => $entry['content'] !== '' || $entry['title'] !== '')
            ->values()
            ->all();
    }

    private function entriesFromRawNotes(?string $rawNotes): array
    {
        $lines = preg_split('/\R/', (string) $rawNotes) ?: [];

        return collect($lines)
            ->map(fn (string $line) => trim(preg_replace('/^\s*[-*•]\s*/', '', $line) ?? ''))
            ->filter()
            ->map(function (string $line) {
                $type = ProtocolEntry::TYPE_INFORMATION;
                $lower = Str::lower($line);

                if (Str::contains($lower, ['beschluss', 'beschlossen', 'entscheidet', 'entscheidung'])) {
                    $type = ProtocolEntry::TYPE_RESOLUTION;
                } elseif (Str::contains($lower, ['diskussion', 'diskutiert', 'beratung', 'besprochen'])) {
                    $type = ProtocolEntry::TYPE_DISCUSSION;
                } elseif (Str::contains($lower, ['wiedervorlage', 'nächste sitzung', 'naechste sitzung', 'erneut beraten'])) {
                    $type = ProtocolEntry::TYPE_FOLLOW_UP;
                } elseif (Str::contains($lower, ['organisiert', 'erledigt', 'kümmert', 'kuemmert', 'bis '])) {
                    $type = ProtocolEntry::TYPE_TASK;
                } elseif (Str::contains($lower, ['termin', 'braukurs', 'veranstaltung']) || preg_match('/\b\d{1,2}\.\d{1,2}\.(\d{2}|\d{4})\b/', $line)) {
                    $type = ProtocolEntry::TYPE_DATE;
                }

                return [
                    'type' => $type,
                    'agenda_title' => '',
                    'title' => Str::limit($line, 70, ''),
                    'content' => $line,
                    'responsible_name' => '',
                    'due_date' => null,
                    'scheduled_date' => null,
                    'visible_in_protocol' => true,
                ];
            })
            ->values()
            ->all();
    }

    private function noteLinesWithContext(?string $rawNotes): array
    {
        $lines = preg_split('/\R/', (string) $rawNotes) ?: [];
        $currentAgendaIndex = null;
        $items = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\s*[-*•]\s*/', '', $line) ?? '');

            if ($line === '') {
                continue;
            }

            if (preg_match('/^TOP\s*(\d+)(?:[\).:-]\s*)?(.*)$/i', $line, $match) === 1) {
                $currentAgendaIndex = max(0, ((int) $match[1]) - 1);
                $remainder = trim($match[2] ?? '');

                if ($remainder === '') {
                    continue;
                }

                $line = $remainder;
            }

            $items[] = [
                'line' => $line,
                'agenda_index' => $currentAgendaIndex,
            ];
        }

        return $items;
    }

    private function protocolEntryTypeRank(string $type): int
    {
        return [
            ProtocolEntry::TYPE_INFORMATION => 0,
            ProtocolEntry::TYPE_DISCUSSION => 1,
            ProtocolEntry::TYPE_FOLLOW_UP => 2,
            ProtocolEntry::TYPE_DATE => 3,
            ProtocolEntry::TYPE_TASK => 4,
            ProtocolEntry::TYPE_RESOLUTION => 5,
        ][$type] ?? 0;
    }

    private function strongerProtocolEntryType(string $currentType, string $newType): string
    {
        return $this->protocolEntryTypeRank($newType) > $this->protocolEntryTypeRank($currentType)
            ? $newType
            : $currentType;
    }

    private function normalizedWordsForMatch(string $value): array
    {
        $value = Str::lower($value);
        $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
        $words = preg_split('/[^a-z0-9]+/i', $value) ?: [];

        return collect($words)
            ->filter(fn (string $word) => strlen($word) >= 4)
            ->unique()
            ->values()
            ->all();
    }

    private function bestAgendaEntryIndexForLine(string $line, array $agendaEntries): ?int
    {
        $lineWords = $this->normalizedWordsForMatch($line);
        $bestIndex = null;
        $bestScore = 0;

        foreach ($agendaEntries as $index => $entry) {
            $titleWords = $this->normalizedWordsForMatch($entry['title'] ?? '');
            $score = count(array_intersect($lineWords, $titleWords));

            if ($score > $bestScore) {
                $bestIndex = $index;
                $bestScore = $score;
            }
        }

        return $bestScore > 0 ? $bestIndex : null;
    }

    private function entryFromNoteLine(string $line): array
    {
        return $this->entriesFromRawNotes($line)[0] ?? [
            'type' => ProtocolEntry::TYPE_INFORMATION,
            'agenda_title' => '',
            'title' => Str::limit($line, 70, ''),
            'content' => $line,
            'responsible_name' => '',
            'due_date' => null,
            'scheduled_date' => null,
            'visible_in_protocol' => true,
        ];
    }

    private function entriesFromAgendaAndNotes(?string $rawAgenda, ?string $rawNotes): array
    {
        $agendaEntries = $this->entriesFromAgenda($rawAgenda);

        if (empty($agendaEntries)) {
            return $this->entriesFromRawNotes($rawNotes);
        }

        $entries = $agendaEntries;

        foreach ($this->noteLinesWithContext($rawNotes) as $item) {
            $noteEntry = $this->entryFromNoteLine($item['line']);
            $targetIndex = $item['agenda_index'];

            if ($targetIndex === null || ! array_key_exists($targetIndex, $entries)) {
                $targetIndex = $this->bestAgendaEntryIndexForLine($item['line'], $agendaEntries);
            }

            if ($targetIndex === null || ! array_key_exists($targetIndex, $entries)) {
                $entries[] = $noteEntry;
                continue;
            }

            $entries[$targetIndex]['content'] = trim(collect([
                $entries[$targetIndex]['content'] ?? '',
                $noteEntry['content'],
            ])->filter()->implode("\n"));
            $entries[$targetIndex]['type'] = $this->strongerProtocolEntryType($entries[$targetIndex]['type'], $noteEntry['type']);
            $entries[$targetIndex]['responsible_name'] = $entries[$targetIndex]['responsible_name'] ?: $noteEntry['responsible_name'];
            $entries[$targetIndex]['due_date'] = $entries[$targetIndex]['due_date'] ?: $noteEntry['due_date'];
            $entries[$targetIndex]['scheduled_date'] = $entries[$targetIndex]['scheduled_date'] ?: $noteEntry['scheduled_date'];
        }

        return array_values($entries);
    }

    private function entriesFromAgenda(?string $rawAgenda): array
    {
        $lines = preg_split('/\R/', (string) $rawAgenda) ?: [];

        return collect($lines)
            ->map(fn (string $line) => trim(preg_replace('/^\s*(?:[-*•]|\d+[\).]|TOP\s*\d+[\).:-]?)\s*/i', '', $line) ?? ''))
            ->filter()
            ->map(fn (string $line) => [
                'type' => ProtocolEntry::TYPE_INFORMATION,
                'agenda_title' => Str::limit($line, 90, ''),
                'title' => Str::limit($line, 90, ''),
                'content' => '',
                'responsible_name' => '',
                'due_date' => null,
                'scheduled_date' => null,
                'visible_in_protocol' => true,
            ])
            ->values()
            ->all();
    }

    private function buildContentFromEntries(array $entries): string
    {
        if (empty($entries)) {
            return '';
        }

        return collect($entries)
            ->filter(fn (array $entry) => $entry['visible_in_protocol'])
            ->map(function (array $entry) {
                $label = ProtocolEntry::typeLabelFor($entry['type']);
                $agendaTitle = trim((string) ($entry['agenda_title'] ?? ''));
                $title = $entry['title'] !== '' ? e($entry['title']) : $label;
                $content = nl2br(e($entry['content']));
                $meta = collect([
                    $entry['responsible_name'] !== '' ? 'Verantwortlich: ' . e($entry['responsible_name']) : null,
                    $entry['due_date'] ? 'Fällig am: ' . e($entry['due_date']) : null,
                    $entry['scheduled_date'] ? 'Termin: ' . e($entry['scheduled_date']) : null,
                ])->filter()->implode(' · ');

                return ($agendaTitle !== '' ? '<h2>' . e($agendaTitle) . '</h2>' : '')
                    . '<h3>' . $title . '</h3><p><strong>' . e($label) . '</strong></p><p>' . $content . '</p>'
                    . ($meta !== '' ? '<p><em>' . $meta . '</em></p>' : '');
            })
            ->implode("\n");
    }

    private function syncProtocolEntries(Protocol $protocol, array $entries): void
    {
        $previousEntryIds = $protocol->entries()->pluck('id')->map(fn ($id) => (string) $id)->all();

        if (! empty($previousEntryIds) && Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'related_type') && Schema::hasColumn('tasks', 'related_id')) {
            Task::query()
                ->where('tenant_id', $protocol->tenant_id)
                ->where('related_type', ProtocolEntry::class)
                ->whereIn('related_id', $previousEntryIds)
                ->delete();
        }

        $protocol->entries()->delete();

        foreach ($entries as $position => $entry) {
            $protocol->entries()->create([
                'tenant_id' => $protocol->tenant_id,
                'agenda_title' => ($entry['agenda_title'] ?? '') !== '' ? $entry['agenda_title'] : null,
                'type' => $entry['type'],
                'title' => $entry['title'] !== '' ? $entry['title'] : null,
                'content' => $entry['content'] !== '' ? $entry['content'] : ($entry['title'] ?: ProtocolEntry::typeLabelFor($entry['type'])),
                'responsible_name' => $entry['responsible_name'] !== '' ? $entry['responsible_name'] : null,
                'due_date' => $entry['due_date'] ?: null,
                'scheduled_date' => $entry['scheduled_date'] ?: null,
                'visible_in_protocol' => $entry['visible_in_protocol'],
                'position' => $position,
            ]);
        }

        $protocol->load('entries');
        $this->syncTasksFromProtocolEntries($protocol);
    }

    private function syncTasksFromProtocolEntries(Protocol $protocol): void
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'related_type') || ! Schema::hasColumn('tasks', 'related_id')) {
            return;
        }

        $existingTasks = Task::query()
            ->where('tenant_id', $protocol->tenant_id)
            ->where('related_type', ProtocolEntry::class)
            ->whereIn('related_id', $protocol->entries->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->get()
            ->keyBy('related_id');

        foreach ($protocol->entries as $entry) {
            if ($entry->type !== ProtocolEntry::TYPE_TASK) {
                continue;
            }

            $title = $entry->title ?: Str::limit($entry->content, 80, '');

            Task::query()->updateOrCreate(
                [
                    'tenant_id' => $protocol->tenant_id,
                    'related_type' => ProtocolEntry::class,
                    'related_id' => (string) $entry->id,
                ],
                [
                    'project_id' => null,
                    'title' => $title ?: 'Aufgabe aus Protokoll',
                    'description' => trim($entry->content . ($entry->responsible_name ? "\n\nVerantwortlich: " . $entry->responsible_name : '')),
                    'plan_end' => $entry->due_date,
                    'status' => optional($existingTasks->get((string) $entry->id))->status ?: 'open',
                    'percent_done' => optional($existingTasks->get((string) $entry->id))->percent_done ?? 0,
                    'assignee_id' => null,
                    'created_by' => $protocol->user_id,
                    'priority' => 3,
                    'type' => 'task',
                ]
            );
        }
    }

    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        $search = trim((string) request('search', ''));
        $type = trim((string) request('type', ''));
        $status = trim((string) request('status', ''));

        $baseQuery = Protocol::where('tenant_id', $tenantId)
            ->whereNull('archived_at');

        $typeOptions = (clone $baseQuery)
            ->whereNotNull('type')
            ->orderBy('type')
            ->pluck('type')
            ->filter()
            ->unique()
            ->values();

        $sentProtocolIds = TemplateDispatchLog::query()
            ->where('tenant_id', $tenantId)
            ->where('action', 'protocol_sent')
            ->whereIn('channel', ['mail'])
            ->get()
            ->map(fn (TemplateDispatchLog $log) => (int) data_get($log->meta, 'protocol_id'))
            ->filter()
            ->unique()
            ->values();

        $protocolTotalCount = (clone $baseQuery)->count();
        $sentProtocolsCount = $sentProtocolIds->count();

        $protocols = (clone $baseQuery)
            ->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('type', 'like', '%' . $search . '%')
                        ->orWhere('location', 'like', '%' . $search . '%')
                        ->orWhere('content', 'like', '%' . $search . '%')
                        ->orWhere('raw_agenda', 'like', '%' . $search . '%')
                        ->orWhere('raw_notes', 'like', '%' . $search . '%')
                        ->orWhere('resolutions', 'like', '%' . $search . '%')
                        ->orWhere('next_meeting', 'like', '%' . $search . '%');
                });
            })
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status === 'sent', function ($query) use ($sentProtocolIds) {
                $sentProtocolIds->isEmpty()
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('id', $sentProtocolIds->all());
            })
            ->when($status === 'open', function ($query) use ($sentProtocolIds) {
                if ($sentProtocolIds->isNotEmpty()) {
                    $query->whereNotIn('id', $sentProtocolIds->all());
                }
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $dispatchLogs = TemplateDispatchLog::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('action', 'protocol_sent')
            ->whereIn('channel', ['mail'])
            ->get()
            ->filter(fn (TemplateDispatchLog $log) => filled(data_get($log->meta, 'protocol_id')))
            ->groupBy(fn (TemplateDispatchLog $log) => (int) data_get($log->meta, 'protocol_id'));

        $protocols->getCollection()->transform(function (Protocol $protocol) use ($dispatchLogs) {
            $logs = $dispatchLogs->get($protocol->id, collect())->sortByDesc('dispatched_at')->values();

            $protocol->dispatch_count = $logs->count();
            $protocol->last_dispatched_at = optional($logs->first())->dispatched_at;
            $protocol->last_recipient_name = optional($logs->first())->recipient_name;
            $protocol->last_recipient_reference = optional($logs->first())->recipient_reference;

            return $protocol;
        });

        return view('protocols.index', compact(
            'protocols',
            'protocolTotalCount',
            'sentProtocolsCount',
            'typeOptions',
            'search',
            'type',
            'status',
        ));
    }

    public function create()
    {
        $members = Member::forCurrentTenant()
            ->whereNull('archived_at')
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
            'raw_agenda'        => 'nullable|string',
            'raw_notes'         => 'nullable|string',
            'content'           => 'nullable|string',
            'resolutions'       => 'nullable|string',
            'next_meeting'      => 'nullable|string',
            'participant_ids'   => 'nullable|array',
            'participant_ids.*' => [
                'integer',
                'exists:members,id',
            ],
            'attachments'       => 'nullable|array',
            'attachments.*'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ] + $this->validatedEntryRules());

        $entries = $this->normalizeProtocolEntries($validated['entries'] ?? []);
        $rawAgenda = trim((string) ($validated['raw_agenda'] ?? ''));
        $rawNotes = trim((string) ($validated['raw_notes'] ?? ''));

        if (empty($entries)) {
            $entries = $this->entriesFromAgendaAndNotes($rawAgenda, $rawNotes);
        }

        $content = trim((string) ($validated['content'] ?? ''));

        if (! empty($entries)) {
            $content = $this->buildContentFromEntries($entries);
        } elseif ($content === '') {
            $content = $this->buildContentFromEntries($entries);
        }

        if ($content === '' && empty($entries)) {
            return back()
                ->withErrors(['content' => 'Bitte erfasse mindestens einen Protokollpunkt oder einen Protokolltext.'])
                ->withInput();
        }

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
            'raw_agenda'    => $rawAgenda !== '' ? $rawAgenda : null,
            'raw_notes'     => $rawNotes !== '' ? $rawNotes : null,
            'content'       => $content,
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

        $this->syncProtocolEntries($protocol, $entries);

        $participantIds = collect($validated['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedParticipantIds = Member::forCurrentTenant()
            ->whereNull('archived_at')
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

        $protocol->loadMissing(['entries', 'participants', 'user']);

        return view('protocols.show', compact('protocol', 'dispatchLogs'));
    }

    public function edit(Protocol $protocol)
    {
        if ($protocol->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $members = Member::forCurrentTenant()
            ->whereNull('archived_at')
            ->orderBy('last_name')
            ->get();

        $protocol->loadMissing('entries');
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
            'raw_agenda'        => 'nullable|string',
            'raw_notes'         => 'nullable|string',
            'content'           => 'nullable|string',
            'resolutions'       => 'nullable|string',
            'next_meeting'      => 'nullable|string',
            'participant_ids'   => 'nullable|array',
            'participant_ids.*' => [
                'integer',
                'exists:members,id',
            ],
            'attachments'       => 'nullable|array',
            'attachments.*'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ] + $this->validatedEntryRules());

        $entries = $this->normalizeProtocolEntries($validated['entries'] ?? []);
        $rawAgenda = trim((string) ($validated['raw_agenda'] ?? ''));
        $rawNotes = trim((string) ($validated['raw_notes'] ?? ''));

        if (empty($entries)) {
            $entries = $this->entriesFromAgendaAndNotes($rawAgenda, $rawNotes);
        }

        $content = trim((string) ($validated['content'] ?? ''));

        if (! empty($entries)) {
            $content = $this->buildContentFromEntries($entries);
        } elseif ($content === '') {
            $content = $this->buildContentFromEntries($entries);
        }

        if ($content === '' && empty($entries)) {
            return back()
                ->withErrors(['content' => 'Bitte erfasse mindestens einen Protokollpunkt oder einen Protokolltext.'])
                ->withInput();
        }

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
            'raw_agenda'    => $rawAgenda !== '' ? $rawAgenda : null,
            'raw_notes'     => $rawNotes !== '' ? $rawNotes : null,
            'content'       => $content,
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

        $this->syncProtocolEntries($protocol, $entries);

        $participantIds = collect($validated['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedParticipantIds = Member::forCurrentTenant()
            ->whereNull('archived_at')
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
            ->with('success', 'Protokoll wurde gelöscht.');
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
            ->whereNull('archived_at')
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
            ->whereNull('archived_at')
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
                    'members' => 'Bitte wähle mindestens ein Mitglied, einen Kontakt oder eine freie E-Mail-Adresse aus.',
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
            ->with('success', $sentCount . ' Empfänger haben das Protokoll per Mail erhalten.');
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
