<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentController extends Controller
{
    public function create(Request $request, Project $project)
    {
        $user = $request->user();
        if (!$user || (string)$project->tenant_id !== (string)$user->tenant_id) {
            abort(404);
        }

        return view('documents.project-create', [
            'title'   => 'Dokument hochladen',
            'project' => $project,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $user = $request->user();
        if (!$user || (string)$project->tenant_id !== (string)$user->tenant_id) {
            abort(404);
        }

        $data = $request->validate([
            'file' => ['required','file','max:20480'], // ~20 MB
        ]);

        $file = $data['file'];
        $disk = 'local';
        $dir  = 'projects/' . (string) $user->tenant_id . '/' . (string) $project->id;

        $storedPath = $file->store($dir, $disk);

        ProjectDocument::create([
            'tenant_id'     => $user->tenant_id,
            'project_id'    => (string)$project->id,
            'user_id'       => $user->id,
            'disk'          => $disk,
            'path'          => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'size'          => $file->getSize(),
            'mime_type'     => $file->getClientMimeType(),
        ]);

        return redirect()->route('projects.show', $project)->with('success', 'Dokument hochgeladen.');
    }

    public function download(Request $request, Project $project, ProjectDocument $document)
    {
        $user = $request->user();
        if (
            !$user ||
            (string)$project->tenant_id !== (string)$user->tenant_id ||
            (string)$document->project_id !== (string)$project->id ||
            (string)$document->tenant_id !== (string)$user->tenant_id
        ) {
            abort(404);
        }

        if (!Storage::disk($document->disk)->exists($document->path)) {
            abort(404);
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, Project $project, ProjectDocument $document)
    {
        $user = $request->user();
        if (
            !$user ||
            (string)$project->tenant_id !== (string)$user->tenant_id ||
            (string)$document->project_id !== (string)$project->id ||
            (string)$document->tenant_id !== (string)$user->tenant_id
        ) {
            abort(404);
        }

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return redirect()->route('projects.show', $project)->with('success', 'Dokument gelöscht.');
    }
}
