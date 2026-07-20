<?php

namespace App\Http\Controllers;

use App\Models\{Project, Task, User};
use App\Services\TenantMailConfigurator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private readonly TenantMailConfigurator $tenantMailConfigurator,
    ) {
    }

    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $activeFilter = $request->string('filter')->toString();
        $allowedFilters = ['all', 'mine', 'open', 'done'];

        if (! in_array($activeFilter, $allowedFilters, true)) {
            $activeFilter = 'all';
        }

        $today = now()->startOfDay();
        $nextWeek = now()->copy()->addDays(7)->endOfDay();

        $baseQuery = Task::query()
            ->where('tenant_id', $tenantId)
            ->with(['assignee', 'creator', 'project'])
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderBy('plan_end')
            ->orderBy('follow_up_at')
            ->orderByDesc('created_at');

        $tasks = (clone $baseQuery)->get();

        $openTasks = $tasks->filter(fn (Task $task) => ! $task->isDone())->values();
        $myTasks = $openTasks->filter(fn (Task $task) => (int) $task->assignee_id === (int) auth()->id())->values();
        $dueToday = $openTasks->filter(fn (Task $task) => optional($task->plan_end)?->isSameDay($today))->values();
        $overdue = $openTasks->filter(fn (Task $task) => optional($task->plan_end)?->lt($today))->values();
        $upcoming = $openTasks->filter(fn (Task $task) => optional($task->plan_end)?->gt($today) && optional($task->plan_end)?->lte($nextWeek))->values();
        $followUps = $openTasks->filter(fn (Task $task) => $task->follow_up_at !== null)->sortBy('follow_up_at')->values();
        $followUpsReady = $followUps->filter(fn (Task $task) => optional($task->follow_up_at)?->lte($today))->values();
        $recentlyCompleted = $tasks->filter(fn (Task $task) => $task->isDone())->sortByDesc('completed_at')->take(8)->values();
        $doneTasks = $tasks->filter(fn (Task $task) => $task->isDone())->values();

        $filteredTasks = match ($activeFilter) {
            'mine' => $myTasks,
            'open' => $openTasks,
            'done' => $doneTasks,
            default => $tasks,
        };

        $stats = [
            'open' => $openTasks->count(),
            'mine' => $myTasks->count(),
            'due_today' => $dueToday->count(),
            'overdue' => $overdue->count(),
            'follow_up_ready' => $followUpsReady->count(),
        ];

        $filterOptions = [
            ['key' => 'all', 'label' => 'Alle', 'count' => $tasks->count()],
            ['key' => 'mine', 'label' => 'Meine', 'count' => $myTasks->count()],
            ['key' => 'open', 'label' => 'Offen', 'count' => $openTasks->count()],
            ['key' => 'done', 'label' => 'Erledigt', 'count' => $doneTasks->count()],
        ];

        return view('tasks.index', compact(
            'tasks',
            'openTasks',
            'myTasks',
            'dueToday',
            'overdue',
            'upcoming',
            'followUps',
            'followUpsReady',
            'recentlyCompleted',
            'filteredTasks',
            'filterOptions',
            'activeFilter',
            'stats'
        ));
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $selectedProject = $this->resolveRequestedProject($request->query('project'));

        return view('tasks.create', [
            'title' => 'Neue Aufgabe',
            'task' => new Task([
                'project_id' => $selectedProject?->id,
                'status' => 'open',
                'priority' => 3,
                'percent_done' => 0,
                'type' => 'task',
            ]),
            'users' => $this->tenantUsers($tenantId),
            'projects' => $this->tenantProjects($tenantId),
            'selectedProject' => $selectedProject,
            'backUrl' => $selectedProject ? route('projects.show', $selectedProject) : route('tasks.index'),
            'submitRoute' => route('tasks.store'),
            'submitLabel' => 'Aufgabe anlegen',
            'pageTitle' => 'Neue Aufgabe',
            'pageIntro' => 'Lege eine Aufgabe an, weise sie direkt zu und verknüpfe sie nur dann mit einem Projekt, wenn das wirklich hilft.',
        ]);
    }

    public function createFromProject(Project $project)
    {
        $this->ensureProjectAccess($project);

        return redirect()->route('tasks.create', ['project' => $project->id]);
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $this->validatedTaskData($request, $tenantId);

        $task = Task::create($this->buildTaskPayload($data));

        $this->sendDelegationMail($task, auth()->user(), true);

        return $this->redirectAfterSave($task, 'Aufgabe wurde angelegt.');
    }

    public function storeFromProject(Request $request, Project $project)
    {
        $this->ensureProjectAccess($project);

        $request->merge(['project_id' => $project->id]);

        return $this->store($request);
    }

    public function edit(Task $task)
    {
        $this->ensureTaskAccess($task);

        $tenantId = auth()->user()->tenant_id;
        $task->loadMissing(['project', 'assignee']);

        return view('tasks.edit', [
            'title' => 'Aufgabe bearbeiten',
            'task' => $task,
            'users' => $this->tenantUsers($tenantId),
            'projects' => $this->tenantProjects($tenantId),
            'selectedProject' => $task->project,
            'backUrl' => $task->project ? route('projects.show', $task->project) : route('tasks.index'),
            'submitRoute' => route('tasks.update', $task),
            'submitLabel' => 'Aufgabe speichern',
            'pageTitle' => 'Aufgabe bearbeiten',
            'pageIntro' => 'Halte Titel, Zuständigkeit und Fälligkeit sauber. Der Rest ist optionaler Kontext.',
        ]);
    }

    public function editFromProject(Project $project, Task $task)
    {
        $this->ensureProjectAccess($project);
        $this->ensureTaskAccess($task);

        if ((string) $task->project_id !== (string) $project->id) {
            abort(404);
        }

        return redirect()->route('tasks.edit', $task);
    }

    public function update(Request $request, Task $task)
    {
        $this->ensureTaskAccess($task);

        $tenantId = auth()->user()->tenant_id;
        $data = $this->validatedTaskData($request, $tenantId, true);
        $previousAssigneeId = $task->assignee_id;

        $task->update($this->buildTaskPayload($data, $task));
        $task->refresh();

        $assigneeChanged = (string) $previousAssigneeId !== (string) ($task->assignee_id ?? '');

        if ($assigneeChanged) {
            $this->sendDelegationMail($task, auth()->user(), false);
        }

        return $this->redirectAfterSave($task, 'Aufgabe wurde aktualisiert.');
    }

    public function updateFromProject(Request $request, Project $project, Task $task)
    {
        $this->ensureProjectAccess($project);
        $this->ensureTaskAccess($task);

        if ((string) $task->project_id !== (string) $project->id) {
            abort(404);
        }

        return $this->update($request, $task);
    }

    private function validatedTaskData(Request $request, int|string $tenantId, bool $isUpdate = false): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', 'string', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'plan_start' => ['nullable', 'date'],
            'plan_end' => ['nullable', 'date', 'after_or_equal:plan_start'],
            'follow_up_at' => ['nullable', 'date'],
            'status' => [$isUpdate ? 'required' : 'nullable', 'in:open,in_progress,blocked,done'],
            'percent_done' => [$isUpdate ? 'required' : 'nullable', 'integer', 'between:0,100'],
            'priority' => [$isUpdate ? 'required' : 'nullable', 'integer', 'between:1,5'],
            'type' => [$isUpdate ? 'required' : 'nullable', 'in:task,milestone'],
            'related_type' => ['nullable', 'string', 'max:255'],
            'related_id' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function buildTaskPayload(array $data, ?Task $task = null): array
    {
        $payload = array_merge([
            'tenant_id' => auth()->user()->tenant_id,
            'status' => 'open',
            'percent_done' => 0,
            'priority' => 3,
            'type' => 'task',
            'created_by' => $task?->created_by ?: auth()->id(),
        ], $data);

        if (($payload['status'] ?? 'open') === 'done') {
            $payload['completed_at'] = $task?->completed_at ?: now();
            $payload['percent_done'] = 100;
        } else {
            $payload['completed_at'] = null;
        }

        return $payload;
    }

    private function redirectAfterSave(Task $task, string $message)
    {
        $task->loadMissing('project');

        if ($task->project) {
            return redirect()->route('projects.show', $task->project)->with('success', $message);
        }

        return redirect()->route('tasks.index')->with('success', $message);
    }

    private function resolveRequestedProject(?string $projectId): ?Project
    {
        if (blank($projectId)) {
            return null;
        }

        return Project::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->find($projectId);
    }

    private function tenantUsers(int|string $tenantId)
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function tenantProjects(int|string $tenantId)
    {
        return Project::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    private function ensureProjectAccess(Project $project): void
    {
        if ((string) auth()->user()->tenant_id !== (string) $project->tenant_id) {
            abort(404);
        }
    }

    private function ensureTaskAccess(Task $task): void
    {
        if ((string) auth()->user()->tenant_id !== (string) $task->tenant_id) {
            abort(404);
        }
    }

    private function sendDelegationMail(Task $task, User $actor, bool $isNewTask): void
    {
        $task->loadMissing(['assignee', 'project', 'creator']);

        $assignee = $task->assignee;
        $tenant = $actor->tenant;

        if (! $assignee || blank($assignee->email) || (int) $assignee->id === (int) $actor->id || ! $tenant) {
            return;
        }

        $this->tenantMailConfigurator->apply($tenant);

        $projectName = $task->project?->name ?: 'Ohne Projekt';
        $subject = $isNewTask
            ? 'Neue Aufgabe fuer dich: ' . $task->title
            : 'Aufgabe neu zugewiesen: ' . $task->title;

        $taskUrl = route('tasks.edit', $task);
        $dueDate = $task->plan_end?->format('d.m.Y');
        $followUpDate = $task->follow_up_at?->format('d.m.Y');
        $description = filled($task->description)
            ? nl2br(e(Str::limit($task->description, 1200)))
            : null;

        $body = '<p>Hallo ' . e($assignee->name ?: 'liebes Teammitglied') . ',</p>';
        $body .= '<p>' . e($actor->name) . ' hat dir ';
        $body .= $isNewTask ? 'eine neue Aufgabe' : 'eine Aufgabe';
        $body .= ' zugewiesen.</p>';
        $body .= '<p><strong>Titel:</strong> ' . e($task->title) . '<br>';
        $body .= '<strong>Status:</strong> ' . e($this->statusLabel($task->status)) . '<br>';
        $body .= '<strong>Prioritaet:</strong> ' . e($this->priorityLabel($task->priority)) . '<br>';
        $body .= '<strong>Projekt:</strong> ' . e($projectName) . '<br>';

        if ($dueDate) {
            $body .= '<strong>Faellig bis:</strong> ' . e($dueDate) . '<br>';
        }

        if ($followUpDate) {
            $body .= '<strong>Wiedervorlage:</strong> ' . e($followUpDate) . '<br>';
        }

        $body .= '</p>';

        if ($description) {
            $body .= '<p><strong>Beschreibung:</strong><br>' . $description . '</p>';
        }

        $body .= '<p><a href="' . e($taskUrl) . '">Aufgabe in Clubano oeffnen</a></p>';
        $body .= '<p>Viele Gruesse<br>' . e($tenant->name ?: 'Clubano') . '</p>';

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;

        try {
            Mail::send('mail.layout', [
                'body' => $body,
                'tenant' => $tenant,
            ], function ($mail) use ($assignee, $subject, $fromAddress, $fromName, $replyToAddress, $tenant) {
                $mail->to($assignee->email, $assignee->name ?: null)
                    ->subject($subject)
                    ->from($fromAddress, $fromName);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?? $fromName);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Aufgaben-Delegationsmail fehlgeschlagen', [
                'task_id' => $task->id,
                'assignee_id' => $assignee->id,
                'assignee_email' => $assignee->email,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'open' => 'Offen',
            'in_progress' => 'In Arbeit',
            'blocked' => 'Blockiert',
            'done' => 'Erledigt',
            default => 'Offen',
        };
    }

    private function priorityLabel(?int $priority): string
    {
        return match ($priority) {
            1 => 'Sehr hoch',
            2 => 'Hoch',
            3 => 'Normal',
            4 => 'Niedrig',
            5 => 'Sehr niedrig',
            default => 'Normal',
        };
    }
}
