<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateDispatchLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TemplateDispatchLogController extends Controller
{
    private function filteredQuery(Request $request, int $tenantId)
    {
        $query = TemplateDispatchLog::query()
            ->where('tenant_id', $tenantId)
            ->with(['template', 'creator', 'member', 'contact']);

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->integer('template_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->string('search')) . '%';

            $query->where(function ($inner) use ($search) {
                $inner->where('recipient_name', 'like', $search)
                    ->orWhere('recipient_reference', 'like', $search)
                    ->orWhere('subject', 'like', $search);
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $query = $this->filteredQuery($request, $tenantId);

        $logs = $query
            ->latest('dispatched_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $templates = Template::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $statsBase = TemplateDispatchLog::query()->where('tenant_id', $tenantId);

        $stats = [
            'total' => (clone $statsBase)->count(),
            'mail' => (clone $statsBase)->where('channel', 'mail')->count(),
            'letter' => (clone $statsBase)->where('channel', 'letter')->count(),
            'today' => (clone $statsBase)->whereDate('dispatched_at', today())->count(),
            'opened' => (clone $statsBase)->where('open_count', '>', 0)->count(),
            'clicked' => (clone $statsBase)->where('click_count', '>', 0)->count(),
        ];

        return view('templates.dispatch-log', compact('logs', 'templates', 'stats'));
    }

    public function exportPdf(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $tenant = auth()->user()->tenant;

        $logs = $this->filteredQuery($request, $tenantId)
            ->latest('dispatched_at')
            ->latest('id')
            ->get();

        $stats = [
            'total' => $logs->count(),
            'mail' => $logs->where('channel', 'mail')->count(),
            'letter' => $logs->where('channel', 'letter')->count(),
            'opened' => $logs->where('open_count', '>', 0)->count(),
            'clicked' => $logs->where('click_count', '>', 0)->count(),
        ];

        $filters = [
            'search' => trim((string) $request->string('search')),
            'channel' => trim((string) $request->string('channel')),
            'template_id' => $request->filled('template_id') ? $request->integer('template_id') : null,
        ];

        $pdf = Pdf::loadView('pdf.dispatch-log', [
            'tenant' => $tenant,
            'logs' => $logs,
            'stats' => $stats,
            'filters' => $filters,
            'generatedAt' => now(),
            'generatedBy' => auth()->user(),
        ])->setPaper('a4', 'landscape');

        $fileName = 'versandprotokoll-' . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($fileName);
    }
}
