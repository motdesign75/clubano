<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Project;
use App\Models\Protocol;
use App\Services\ReceiptRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $search = trim((string) $request->query('search', ''));
        $category = $request->query('category');
        $status = $request->query('status');
        $due = $request->query('due');

        $documents = Document::query()
            ->with(['uploader', 'member', 'project', 'event', 'protocol', 'invoice'])
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('original_name', 'like', '%' . $search . '%');
                });
            })
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($status, function ($query) use ($status) {
                if ($status === Document::STATUS_ARCHIVED) {
                    $query->whereNotNull('archived_at');
                } else {
                    $query->whereNull('archived_at')->where('status', $status);
                }
            }, fn ($query) => $query->whereNull('archived_at'))
            ->when($due === 'soon', fn ($query) => $query->whereDate('expires_at', '<=', now()->addDays(30)))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $baseQuery = Document::where('tenant_id', $tenantId);
        $receiptInbox = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->whereNull('archived_at')
            ->whereIn('receipt_status', [Document::RECEIPT_NEEDS_REVIEW, Document::RECEIPT_READY])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('documents.index', [
            'documents' => $documents,
            'receiptInbox' => $receiptInbox,
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'due' => $due,
            'categories' => Document::categories(),
            'statuses' => Document::statuses(),
            'documentTotalCount' => (clone $baseQuery)->whereNull('archived_at')->count(),
            'attentionCount' => (clone $baseQuery)->needsAttention()->count(),
            'expiringCount' => (clone $baseQuery)->whereNull('archived_at')->whereDate('expires_at', '<=', now()->addDays(30))->count(),
            'archivedCount' => (clone $baseQuery)->whereNotNull('archived_at')->count(),
            'receiptOpenCount' => (clone $baseQuery)->where('is_booking_receipt', true)->whereIn('receipt_status', [Document::RECEIPT_NEEDS_REVIEW, Document::RECEIPT_READY])->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('documents.create', $this->formData($request));
    }

    public function store(Request $request, ReceiptRecognitionService $recognitionService)
    {
        $data = $this->validatedData($request);
        $file = $data['file'];
        unset($data['file']);
        $receiptData = $this->receiptData($data, $request, $recognitionService, $file);

        $path = $file->store('documents/' . $request->user()->tenant_id . '/' . now()->format('Y/m'), 'local');

        Document::create(array_merge($data, $receiptData, [
            'tenant_id' => $request->user()->tenant_id,
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]));

        return redirect()->route('documents.index')->with('success', filled($receiptData['is_booking_receipt'] ?? false)
            ? 'Beleg wurde abgelegt und in den Beleg-Eingang gelegt.'
            : 'Dokument wurde abgelegt.');
    }

    public function show(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        return view('documents.show', [
            'document' => $document->load(['uploader', 'member', 'project', 'event', 'protocol', 'invoice']),
        ]);
    }

    public function edit(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        return view('documents.edit', $this->formData($request) + [
            'document' => $document,
        ]);
    }

    public function update(Request $request, Document $document, ReceiptRecognitionService $recognitionService)
    {
        $this->authorizeTenant($request, $document);

        $data = $this->validatedData($request, updating: true);
        unset($data['file']);
        $file = $request->file('file');
        $receiptData = $this->receiptData($data, $request, $recognitionService, $file);

        if ($request->hasFile('file')) {
            Storage::disk($document->disk)->delete($document->path);

            $data['disk'] = 'local';
            $data['path'] = $file->store('documents/' . $request->user()->tenant_id . '/' . now()->format('Y/m'), 'local');
            $data['original_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getClientMimeType();
            $data['size'] = $file->getSize();
        }

        $document->update(array_merge($data, $receiptData));

        return redirect()->route('documents.show', $document)->with('success', 'Dokument wurde aktualisiert.');
    }

    public function updateReceipt(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        $validated = $this->validatedReceiptData($request);

        $document->update($validated + [
            'is_booking_receipt' => true,
            'category' => Document::CATEGORY_FINANCE,
            'receipt_status' => Document::RECEIPT_READY,
        ]);

        return back()->with('success', 'Belegdaten wurden geprüft. Der Beleg ist jetzt buchbar.');
    }

    public function prepareTransaction(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        if (! $document->is_booking_receipt) {
            return back()->with('error', 'Dieses Dokument ist nicht als Beleg markiert.');
        }

        return redirect()->route('transactions.create', [
            'context' => 'beleg-eingang',
            'receipt_document_id' => $document->id,
            'date' => $document->recognized_date?->format('Y-m-d') ?? $document->document_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'description' => $document->recognized_vendor ?: $document->title,
            'amount' => $document->recognized_amount,
        ]);
    }

    public function download(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        if (! Storage::disk($document->disk)->exists($document->path)) {
            abort(404);
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function archive(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        $document->update([
            'status' => Document::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);

        return redirect()->route('documents.index')->with('success', 'Dokument wurde archiviert.');
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Dokument wurde gelöscht.');
    }

    protected function formData(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

        return [
            'categories' => Document::categories(),
            'statuses' => collect(Document::statuses())->except(Document::STATUS_ARCHIVED)->all(),
            'members' => Member::where('tenant_id', $tenantId)->whereNull('archived_at')->orderBy('last_name')->orderBy('first_name')->get(),
            'projects' => Project::where('tenant_id', $tenantId)->orderBy('name')->get(),
            'events' => Event::where('tenant_id', $tenantId)->orderByDesc('start')->take(80)->get(),
            'protocols' => Protocol::where('tenant_id', $tenantId)->orderByDesc('created_at')->take(80)->get(),
            'invoices' => Invoice::where('tenant_id', $tenantId)->orderByDesc('invoice_date')->take(80)->get(),
        ];
    }

    protected function validatedData(Request $request, bool $updating = false): array
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Document::categories()))],
            'status' => ['required', Rule::in(array_keys(collect(Document::statuses())->except(Document::STATUS_ARCHIVED)->all()))],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'document_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'file' => [$updating ? 'nullable' : 'required', 'file', 'max:51200'],
            'member_id' => ['nullable', Rule::exists('members', 'id')->where('tenant_id', $tenantId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('tenant_id', $tenantId)],
            'protocol_id' => ['nullable', Rule::exists('protocols', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)],
            'is_booking_receipt' => ['nullable', 'boolean'],
            'recognized_amount' => ['nullable', 'numeric', 'min:0'],
            'recognized_currency' => ['nullable', 'string', 'size:3'],
            'recognized_date' => ['nullable', 'date'],
            'recognized_vendor' => ['nullable', 'string', 'max:255'],
            'recognized_invoice_number' => ['nullable', 'string', 'max:255'],
        ]);

        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();

        return $data;
    }

    protected function receiptData(array $data, Request $request, ReceiptRecognitionService $recognitionService, mixed $file = null): array
    {
        $isReceipt = (bool) ($data['is_booking_receipt'] ?? false);

        if (! $isReceipt) {
            return [
                'is_booking_receipt' => false,
                'receipt_status' => Document::RECEIPT_NOT_RELEVANT,
                'recognized_amount' => null,
                'recognized_currency' => null,
                'recognized_date' => null,
                'recognized_vendor' => null,
                'recognized_invoice_number' => null,
                'recognition_source' => null,
                'recognition_notes' => null,
            ];
        }

        $suggestions = $file ? $recognitionService->fromUpload($file) : [];
        $receiptData = $this->validatedReceiptData($request, required: false);

        foreach (['recognized_amount', 'recognized_currency', 'recognized_date', 'recognized_vendor', 'recognized_invoice_number'] as $field) {
            if (blank($receiptData[$field] ?? null) && filled($suggestions[$field] ?? null)) {
                $receiptData[$field] = $suggestions[$field];
            }
        }

        return $receiptData + [
            'is_booking_receipt' => true,
            'category' => Document::CATEGORY_FINANCE,
            'receipt_status' => filled($receiptData['recognized_amount'] ?? null)
                ? Document::RECEIPT_READY
                : Document::RECEIPT_NEEDS_REVIEW,
            'recognized_currency' => $receiptData['recognized_currency'] ?? 'EUR',
            'recognition_source' => $suggestions['recognition_source'] ?? 'Manuell',
            'recognition_notes' => $suggestions['recognition_notes'] ?? null,
        ];
    }

    protected function validatedReceiptData(Request $request, bool $required = true): array
    {
        return $request->validate([
            'recognized_amount' => [$required ? 'required' : 'nullable', 'numeric', 'min:0'],
            'recognized_currency' => ['nullable', 'string', 'size:3'],
            'recognized_date' => ['nullable', 'date'],
            'recognized_vendor' => ['nullable', 'string', 'max:255'],
            'recognized_invoice_number' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function authorizeTenant(Request $request, Document $document): void
    {
        if ((string) $document->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }
    }
}
