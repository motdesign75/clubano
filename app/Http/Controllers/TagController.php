<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::where('tenant_id', app('currentTenant')->id)
                   ->orderBy('name')
                   ->get();

        return view('tags.index', compact('tags'));
    }

    public function create()
    {
        return view('tags.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7', // hex code, z. B. #ff0000
        ]);

        Tag::create([
            'tenant_id' => app('currentTenant')->id,
            'name' => $request->name,
            'color' => $request->color ?? '#4f46e5', // Fallback: Indigo
        ]);

        return redirect()->route('tags.index')->with('success', 'Tag wurde erstellt.');
    }

    public function edit(Tag $tag)
    {
        $this->authorizeTenant($tag);
        return view('tags.edit', compact('tag'));
    }

    public function update(Request $request, Tag $tag)
    {
        $this->authorizeTenant($tag);

        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        $tag->update([
            'name' => $request->name,
            'color' => $request->color ?? '#4f46e5',
        ]);

        return redirect()->route('tags.index')->with('success', 'Tag wurde aktualisiert.');
    }

    public function destroy(Tag $tag)
    {
        $this->authorizeTenant($tag);
        $tag->delete();

        return redirect()->route('tags.index')->with('success', 'Tag wurde gelöscht.');
    }

    private function authorizeTenant(Tag $tag): void
    {
        abort_if($tag->tenant_id !== app('currentTenant')->id, 403, 'Unberechtigter Zugriff.');
    }
}
