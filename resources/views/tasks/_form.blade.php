@php
    $taskProjectId = old('project_id', $task->project_id);
    $taskAssigneeId = old('assignee_id', $task->assignee_id);
    $taskStatus = old('status', $task->status ?? 'open');
    $taskPriority = (string) old('priority', $task->priority ?? 3);
    $taskPercentDone = old('percent_done', $task->percent_done ?? 0);
    $taskType = old('type', $task->type ?? 'task');
@endphp

<form method="post" action="{{ $submitRoute }}" class="space-y-6">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.8fr]">
            <div class="space-y-5">
                <div>
                    <label for="title" class="block text-sm font-medium text-slate-700">Titel</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $task->title) }}" required
                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('title') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Beschreibung</label>
                    <textarea id="description" name="description" rows="6"
                              class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $task->description) }}</textarea>
                    <p class="mt-2 text-sm text-slate-500">Kurz und klar reicht. Alles Weitere kann spaeter wachsen.</p>
                    @error('description') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                <div class="text-sm font-semibold text-slate-900">Direkt zuweisen</div>
                <p class="mt-1 text-sm leading-6 text-slate-600">Wenn du hier jemanden auswählst, kann Clubano die Person sofort informieren.</p>

                <div class="mt-4">
                    <label for="assignee_id" class="block text-sm font-medium text-slate-700">Zuständig</label>
                    <select id="assignee_id" name="assignee_id"
                            class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Noch niemand zuweisen</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) $taskAssigneeId === (string) $user->id)>
                                {{ $user->name }}@if($user->email) · {{ $user->email }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('assignee_id') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                </div>

                <div class="mt-4">
                    <label for="project_id" class="block text-sm font-medium text-slate-700">Projekt</label>
                    <select id="project_id" name="project_id"
                            class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Ohne Projekt führen</option>
                        @foreach($projects as $projectOption)
                            <option value="{{ $projectOption->id }}" @selected((string) $taskProjectId === (string) $projectOption->id)>
                                {{ $projectOption->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-sm text-slate-500">Nur zuordnen, wenn die Aufgabe wirklich zu einem Projekt gehört.</p>
                    @error('project_id') <div class="mt-2 text-sm text-rose-700">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Rahmen setzen</h2>
                <p class="mt-1 text-sm text-slate-500">Nur die Angaben, die fuer Planung und Priorisierung wirklich helfen.</p>
            </div>
        </div>

        <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <label for="plan_start" class="block text-sm font-medium text-slate-700">Start</label>
                <input id="plan_start" name="plan_start" type="date" value="{{ old('plan_start', optional($task->plan_start)->toDateString()) }}"
                       class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="plan_end" class="block text-sm font-medium text-slate-700">Fällig bis</label>
                <input id="plan_end" name="plan_end" type="date" value="{{ old('plan_end', optional($task->plan_end)->toDateString()) }}"
                       class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="follow_up_at" class="block text-sm font-medium text-slate-700">Wiedervorlage</label>
                <input id="follow_up_at" name="follow_up_at" type="date" value="{{ old('follow_up_at', optional($task->follow_up_at)->toDateString()) }}"
                       class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status"
                        class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="open" @selected($taskStatus === 'open')>Offen</option>
                    <option value="in_progress" @selected($taskStatus === 'in_progress')>In Arbeit</option>
                    <option value="blocked" @selected($taskStatus === 'blocked')>Blockiert</option>
                    <option value="done" @selected($taskStatus === 'done')>Erledigt</option>
                </select>
            </div>

            <div>
                <label for="priority" class="block text-sm font-medium text-slate-700">Priorität</label>
                <select id="priority" name="priority"
                        class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="1" @selected($taskPriority === '1')>Sehr hoch</option>
                    <option value="2" @selected($taskPriority === '2')>Hoch</option>
                    <option value="3" @selected($taskPriority === '3')>Normal</option>
                    <option value="4" @selected($taskPriority === '4')>Niedrig</option>
                    <option value="5" @selected($taskPriority === '5')>Sehr niedrig</option>
                </select>
            </div>

            <div>
                <label for="percent_done" class="block text-sm font-medium text-slate-700">Fortschritt in %</label>
                <input id="percent_done" name="percent_done" type="number" min="0" max="100" step="1"
                       value="{{ $taskPercentDone }}"
                       class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-slate-700">Typ</label>
                <select id="type" name="type"
                        class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="task" @selected($taskType === 'task')>Aufgabe</option>
                    <option value="milestone" @selected($taskType === 'milestone')>Meilenstein</option>
                </select>
            </div>
        </div>
    </section>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <a href="{{ $backUrl }}"
           class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Abbrechen
        </a>
        <button class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                type="submit">
            {{ $submitLabel }}
        </button>
    </div>
</form>
