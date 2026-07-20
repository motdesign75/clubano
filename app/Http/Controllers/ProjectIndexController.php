<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;

class ProjectIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $title = 'Projekte';
        $tenantId = $request->user()?->tenant_id;

        $projects = Project::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('projects.index', compact('title', 'projects'));
    }
}
