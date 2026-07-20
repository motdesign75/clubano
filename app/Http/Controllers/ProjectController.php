<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /** Formular: neues Projekt */
    public function create()
    {
        return view('projects.create', [
            'users' => $this->tenantUsers(auth()->user()->tenant_id),
        ]);
    }

    /** Speichern: neues Projekt */
    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', Rule::in(['active','on_hold','done'])],
            'owner_id'    => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            // Diese beiden Felder dürfen fehlen; wir mappen unten auch Alternativnamen
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date'],
        ]);

        // Alternativnamen aus Formular mappen (falls du z. B. "start"/"end"/"beginn"/"ende" verwendest)
        $startsInput = $data['starts_at'] ?? $request->input('start') ?? $request->input('begin') ?? $request->input('beginn');
        $endsInput   = $data['ends_at']   ?? $request->input('end')   ?? $request->input('ende');

        // Robust in DATE-Format wandeln (akzeptiert auch datetime-local -> schneidet Uhrzeit ab)
        $data['starts_at'] = $this->normalizeDate($startsInput);
        $data['ends_at']   = $this->normalizeDate($endsInput);

        // Logische Prüfung: Ende nicht vor Start
        if ($data['starts_at'] && $data['ends_at'] && Carbon::parse($data['ends_at'])->lt(Carbon::parse($data['starts_at']))) {
            return back()
                ->withErrors(['ends_at' => 'Ende darf nicht vor dem Start liegen.'])
                ->withInput();
        }

        // Mandant & Default-Werte
        $data['tenant_id'] = $tenantId;
        $data['owner_id']  = $data['owner_id'] ?? $request->user()->id;
        $data['status']    = $data['status']   ?? 'active';

        $project = Project::create($data);

        return redirect()->route('projects.index')->with('status', 'Projekt wurde angelegt.');
        // Alternativ direkt in die Detailansicht:
        // return redirect()->route('projects.show', $project)->with('success', 'Projekt wurde angelegt.');
    }

    /** Formular: Projekt bearbeiten */
    public function edit(Request $request, Project $project)
    {
        if ((string)$request->user()->tenant_id !== (string)$project->tenant_id) {
            abort(404);
        }

        return view('projects.edit', [
            'project' => $project,
            'users' => $this->tenantUsers($project->tenant_id),
        ]);
    }

    /** Update: Projekt speichern */
    public function update(Request $request, Project $project)
    {
        if ((string)$request->user()->tenant_id !== (string)$project->tenant_id) {
            abort(404);
        }

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['required', Rule::in(['active','on_hold','done'])],
            'owner_id'    => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date'],
        ]);

        $startsInput = $data['starts_at'] ?? $request->input('start') ?? $request->input('begin') ?? $request->input('beginn');
        $endsInput   = $data['ends_at']   ?? $request->input('end')   ?? $request->input('ende');

        $data['starts_at'] = $this->normalizeDate($startsInput);
        $data['ends_at']   = $this->normalizeDate($endsInput);

        if ($data['starts_at'] && $data['ends_at'] && Carbon::parse($data['ends_at'])->lt(Carbon::parse($data['starts_at']))) {
            return back()
                ->withErrors(['ends_at' => 'Ende darf nicht vor dem Start liegen.'])
                ->withInput();
        }

        // Owner optional aktualisieren, sonst unverändert lassen
        $data['owner_id'] = $data['owner_id'] ?? $project->owner_id;

        $project->update($data);

        return redirect()->route('projects.index')->with('status', 'Projekt aktualisiert.');
        // Alternativ:
        // return redirect()->route('projects.show', $project)->with('success', 'Projekt aktualisiert.');
    }

    /** Löschen: Projekt entfernen */
    public function destroy(Request $request, Project $project)
    {
        if ((string)$request->user()->tenant_id !== (string)$project->tenant_id) {
            abort(404);
        }

        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Projekt gelöscht.');
    }

    /**
     * Normalisiert verschiedenste Datums-Eingaben (inkl. datetime-local) auf 'Y-m-d'.
     * Gibt null zurück, wenn leer/ungültig.
     */
    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, config('app.timezone', 'Europe/Berlin'))->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function tenantUsers(int|string $tenantId)
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
