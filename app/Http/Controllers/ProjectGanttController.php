<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectGanttController extends Controller
{
    /**
     * Sicheres Date-only-Format (Y-m-d) aus allem, was nach Datum riecht.
     * Nutzt die App-TZ und gibt null bei Parse-Problemen zurück.
     */
    private function fmtDate($v): ?string
    {
        if (!$v && $v !== '0') return null;
        try {
            return Carbon::parse($v, config('app.timezone', 'Europe/Berlin'))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function val($row, $k)
    {
        return is_array($row)
            ? ($row[$k] ?? null)
            : (is_object($row) && isset($row->{$k}) ? $row->{$k} : null);
    }

    /** Erster nicht-leerer Treffer aus $keys */
    private function first($row, array $keys)
    {
        foreach ($keys as $k) {
            $v = $this->val($row, $k);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return null;
    }

    /** Status-Ermittlung mit gängigen Fallbacks */
    private function statusFrom($row): string
    {
        $s = strtolower((string)($this->first($row, ['status', 'state']) ?? 'open'));
        if ($this->val($row, 'completed_at') || $this->val($row, 'is_done') || $this->val($row, 'done')) return 'done';
        if ($this->val($row, 'is_blocked')   || $this->val($row, 'blocked')) return 'blocked';
        return in_array($s, ['open', 'active', 'in_progress', 'done', 'blocked'], true)
            ? ($s === 'in_progress' ? 'open' : ($s === 'active' ? 'open' : $s))
            : 'open';
    }

    /** clamp 0..100 für Progress */
    private function clampProgress($v): int
    {
        $n = (int)round((float)$v);
        return max(0, min(100, $n));
    }

    /**
     * Tasks dynamisch aus der Tabelle `tasks` ermitteln (ohne Eloquent-Model-Pflicht).
     * Erkennt Start/Ende großzügig (plan_*, start/end, due, date, schedule …).
     */
    private function collectTasksDirect(Project $project, Request $request)
    {
        $out = collect();

        if (!Schema::hasTable('tasks') || !Schema::hasColumn('tasks', 'project_id')) {
            return $out;
        }

        $cols = Schema::getColumnListing('tasks');

        // Spaltenkandidaten
        $nameKeys = array_values(array_filter($cols, fn ($c) => preg_match('/^(name|title|label)$/i', $c)));
        $typeKeys = array_values(array_filter($cols, fn ($c) => preg_match('/^(type|kind)$/i', $c)));

        // Start/End-Kandidaten (breit gefasst, Reihenfolge ist wichtig)
        $startCols = array_values(array_filter($cols, fn ($c) => preg_match('/^(plan_|planned_|schedul|start|begin|from|date|due)/i', $c)));
        $endCols   = array_values(array_filter($cols, fn ($c) => preg_match('/^(plan_|planned_|schedul|end|finish|to|date|due)/i', $c)));

        // Query
        $q = DB::table('tasks')->where('project_id', $project->getKey());
        if (Schema::hasColumn('tasks', 'tenant_id') && $request->user()) {
            $q->where('tenant_id', $request->user()->tenant_id);
        }
        // Ggf. Soft-Deletes berücksichtigen
        if (Schema::hasColumn('tasks', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        $rows = $q->get();

        foreach ($rows as $r) {
            // Start/Ende robust extrahieren
            $start = null;
            foreach ($startCols as $c) { $start = $this->fmtDate($this->val($r, $c)); if ($start) break; }

            $end = null;
            foreach ($endCols as $c)   { $end   = $this->fmtDate($this->val($r, $c)); if ($end)   break; }

            // Fallbacks
            if (!$start && $end) $start = $end;
            if (!$end && $start) $end   = $start;
            if (!$start && !$end) continue;

            $type = strtolower((string)($this->first($r, $typeKeys) ?? 'task'));
            if (!$type && ($this->val($r, 'is_milestone') || $this->val($r, 'milestone'))) {
                $type = 'milestone';
            }

            $progress = $this->first($r, ['progress', 'percent_complete', 'percent_done', 'percent']) ?? 0;

            $out->push([
                'id'       => $this->val($r, 'id') ?? $this->val($r, 'ulid') ?? $this->val($r, 'uuid'),
                'name'     => $this->first($r, $nameKeys) ?? 'Aufgabe',
                'type'     => $type === 'milestone' ? 'milestone' : 'task',
                'status'   => $this->statusFrom($r),
                'start'    => $start,
                'end'      => $end,
                'progress' => $this->clampProgress($progress),
            ]);
        }

        return $out->values();
    }

    /**
     * Milestones optional aus Relationen `milestones` oder `projectMilestones` lesen.
     */
    private function collectMilestones(Project $project, Request $request)
    {
        $out = collect();

        foreach (['milestones', 'projectMilestones'] as $rel) {
            if (!method_exists($project, $rel)) continue;

            try {
                $query = $project->{$rel}();
                // Tenantschutz (falls Relation nicht automatisch scoped)
                if (Schema::hasColumn($query->getModel()->getTable(), 'tenant_id') && $request->user()) {
                    $query->where('tenant_id', $request->user()->tenant_id);
                }
                if (Schema::hasColumn($query->getModel()->getTable(), 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }

                foreach ($query->get() as $m) {
                    $d = $this->fmtDate($this->first($m, ['date', 'on_date', 'starts_at', 'start']));
                    if (!$d) continue;

                    $out->push([
                        'id'      => $m->id,
                        'name'    => $m->name ?? 'Meilenstein',
                        'type'    => 'milestone',
                        'status'  => $this->statusFrom($m),
                        'start'   => $d,
                        'end'     => $d,
                        'progress'=> 100,
                    ]);
                }
                if ($out->count()) break;
            } catch (\Throwable) {
                // Relation existiert, aber fehlerhaft → ignorieren
            }
        }

        return $out->values();
    }

    /**
     * Dependencies erkennen:
     * 1) Separate Tabelle (beliebte Varianten): task_links / tasks_links / dependencies
     *    Spalten: (from_id|from_task_id) -> (to_id|to_task_id) [+ project_id]
     * 2) Oder in tasks: depends_on_id / dependency_id / predecessor_id / parent_id
     */
    private function collectLinks(Project $project, Request $request, array $taskIds): array
    {
        $links = collect();

        // 1) Link-Tabellen prüfen
        foreach (['task_links', 'tasks_links', 'dependencies'] as $table) {
            if (!Schema::hasTable($table)) continue;

            $cols = Schema::getColumnListing($table);

            $fromCol = collect(['from_task_id', 'from_id', 'source_id'])->first(fn ($c) => in_array($c, $cols, true));
            $toCol   = collect(['to_task_id', 'to_id', 'target_id'])->first(fn ($c) => in_array($c, $cols, true));

            if (!$fromCol || !$toCol) continue;

            $q = DB::table($table);

            // Tenants/Project scoping wenn vorhanden
            if (in_array('project_id', $cols, true)) {
                $q->where('project_id', $project->getKey());
            }
            if (in_array('tenant_id', $cols, true) && $request->user()) {
                $q->where('tenant_id', $request->user()->tenant_id);
            }
            if (in_array('deleted_at', $cols, true)) {
                $q->whereNull('deleted_at');
            }

            // Auf Taskmenge beschränken, falls kein project_id vorhanden
            if (!in_array('project_id', $cols, true)) {
                $q->whereIn($fromCol, $taskIds)->whereIn($toCol, $taskIds);
            }

            $rows = $q->get();
            foreach ($rows as $r) {
                $from = $this->val($r, $fromCol);
                $to   = $this->val($r, $toCol);
                if ($from && $to && $from !== $to) {
                    $links->push(['from' => $from, 'to' => $to]);
                }
            }
        }

        // 2) Inline-Dependencies in tasks
        if (Schema::hasTable('tasks')) {
            $taskCols = Schema::getColumnListing('tasks');
            $depCol = collect(['depends_on_id', 'dependency_id', 'predecessor_id', 'parent_id'])
                ->first(fn ($c) => in_array($c, $taskCols, true));

            if ($depCol) {
                $q = DB::table('tasks')
                    ->select('id', $depCol)
                    ->where('project_id', $project->getKey())
                    ->whereIn('id', $taskIds);

                if (in_array('tenant_id', $taskCols, true) && $request->user()) {
                    $q->where('tenant_id', $request->user()->tenant_id);
                }
                if (in_array('deleted_at', $taskCols, true)) {
                    $q->whereNull('deleted_at');
                }

                foreach ($q->get() as $r) {
                    $from = $this->val($r, $depCol);
                    $to   = $this->val($r, 'id');
                    if ($from && $to && $from !== $to && in_array($from, $taskIds, true)) {
                        $links->push(['from' => $from, 'to' => $to]);
                    }
                }
            }
        }

        // Duplikate entfernen
        return $links
            ->unique(fn ($l) => $l['from'] . '>' . $l['to'])
            ->values()
            ->all();
    }

    /**
     * JSON für das Gantt (genutzt in Blade & JS).
     * Struktur:
     * {
     *   project: { id, name, starts_at, ends_at },
     *   tasks: [ {id,name,type:'task',status,start,end,progress} ],
     *   milestones: [ {id,name,type:'milestone',status,start,end,progress:100} ],
     *   links: [ {from,to} ],
     *   _debug?: {...}
     * }
     */
    public function json(Request $request, Project $project)
    {
        // Mandantenschutz
        if (!$request->user() || (string)$request->user()->tenant_id !== (string)$project->tenant_id) {
            abort(404);
        }

        // Daten einsammeln
        $tasksDirect = $this->collectTasksDirect($project, $request);
        $milestones  = $this->collectMilestones($project, $request);

        // Milestones auch aus Tasks extrahieren
        $msFromTasks = $tasksDirect->filter(fn ($t) => ($t['type'] ?? '') === 'milestone');
        $tasksOnly   = $tasksDirect->filter(fn ($t) => ($t['type'] ?? 'task') !== 'milestone')->values();
        $msMerged    = $milestones->merge($msFromTasks)->values();

        // Projektzeitraum
        $pStart = $this->fmtDate($project->starts_at ?? $project->start ?? null);
        $pEnd   = $this->fmtDate($project->ends_at   ?? $project->end   ?? null);

        // Fallback: aus Tasks/Milestones ableiten, wenn Projektzeitraum fehlt
        $allDates = collect();
        foreach ($tasksOnly as $t) { $allDates->push($t['start'], $t['end']); }
        foreach ($msMerged as $m)  { $allDates->push($m['start'], $m['end']); }
        $allDates = $allDates->filter();

        if (!$pStart && $allDates->count()) $pStart = $allDates->min();
        if (!$pEnd   && $allDates->count()) $pEnd   = $allDates->max();

        // Dependencies (optional)
        $taskIds = $tasksOnly->pluck('id')->filter()->values()->all();
        $links   = $this->collectLinks($project, $request, $taskIds);

        $payload = [
            'project' => [
                'id'        => $project->id,
                'name'      => $project->name,
                'starts_at' => $pStart,
                'ends_at'   => $pEnd,
            ],
            'tasks'      => $tasksOnly->all(),
            'milestones' => $msMerged->all(),
            'links'      => $links,
        ];

        if ($request->boolean('debug')) {
            $payload['_debug'] = [
                'out_task_count' => count($payload['tasks']),
                'out_ms_count'   => count($payload['milestones']),
                'out_links'      => count($payload['links']),
                'sample_task'    => $payload['tasks'][0] ?? null,
                'sample_ms'      => $payload['milestones'][0] ?? null,
                'tasks_table'    => Schema::hasTable('tasks') ? Schema::getColumnListing('tasks') : [],
            ];
        }

        return response()->json($payload);
    }
}
