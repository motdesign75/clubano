<?php

namespace App\Http\Controllers;

use App\Models\AppNewsItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppNewsController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $items = AppNewsItem::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByRaw('published_at is null')
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('app-news.index', [
            'items' => $items,
            'status' => $status,
            'statusOptions' => AppNewsItem::statuses(),
            'publishedCount' => AppNewsItem::query()->published()->count(),
            'draftCount' => AppNewsItem::query()->where('status', AppNewsItem::STATUS_DRAFT)->count(),
        ]);
    }

    public function create()
    {
        return view('app-news.create', [
            'item' => new AppNewsItem([
                'status' => AppNewsItem::STATUS_DRAFT,
                'published_at' => now(),
                'push_enabled' => true,
            ]),
            'statusOptions' => AppNewsItem::statuses(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data = $this->normalizePublishingData($data);

        AppNewsItem::create($data);

        return redirect()
            ->route('app-news.index')
            ->with('success', 'App-News wurde angelegt.');
    }

    public function edit(AppNewsItem $appNews)
    {
        return view('app-news.edit', [
            'item' => $appNews,
            'statusOptions' => AppNewsItem::statuses(),
        ]);
    }

    public function update(Request $request, AppNewsItem $appNews)
    {
        $data = $this->validated($request);
        $data = $this->normalizePublishingData($data);

        $appNews->update($data);

        return redirect()
            ->route('app-news.index')
            ->with('success', 'App-News wurde aktualisiert.');
    }

    public function destroy(AppNewsItem $appNews)
    {
        $appNews->delete();

        return redirect()
            ->route('app-news.index')
            ->with('success', 'App-News wurde gelöscht.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'teaser' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:6000'],
            'status' => ['required', Rule::in(array_keys(AppNewsItem::statuses()))],
            'published_at' => ['nullable', 'date'],
            'push_enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizePublishingData(array $data): array
    {
        $data['push_enabled'] = (bool) ($data['push_enabled'] ?? false);

        if ($data['status'] === AppNewsItem::STATUS_PUBLISHED && blank($data['published_at'])) {
            $data['published_at'] = now();
        }

        if ($data['status'] === AppNewsItem::STATUS_DRAFT) {
            $data['push_sent_at'] = null;
        }

        return $data;
    }
}
