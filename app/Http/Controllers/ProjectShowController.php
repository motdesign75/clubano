<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectShowController extends Controller
{
    public function __invoke(Request $request, Project $project)
    {
        // Mandantenschutz
        $user = $request->user();
        if (!$user || (string)$project->tenant_id !== (string)$user->tenant_id) {
            abort(404);
        }

        $tasks = $project->tasks()
            ->orderBy('plan_start')
            ->orderBy('title')
            ->get();

        // Dokumente (neu)
        $documents = $project->documents()
            ->latest()
            ->get();

        return view('projects.show', [
            'title'     => 'Projekt: ' . $project->name,
            'project'   => $project,
            'tasks'     => $tasks,
            'documents' => $documents,
        ]);
    }
}
