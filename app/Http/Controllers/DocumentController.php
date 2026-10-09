<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Project;
use App\Models\Protocol;
use App\Services\ReceiptRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $folder = $request->query('folder', 'all');
        $folderId = is_numeric($folder) ? (int) $folder : null;

        $documents = Document::query()
            ->with(['uploader', 'member', 'project', 'event', 'protocol', 'invoice', 'folder.parent'])
            ->where('tenant_id', $tenantId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('original_name', 'like', '%' . $search . '%');
                });
            })
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($folder === 'none', fn ($query) => $query->whereNull('folder_id'))
            ->when($folderId, fn ($query) => $query->where('folder_id', $folderId))
            ->when($status, function ($query) use ($status) {
                if ($status === Document::STATUS_ARCHIVED) {
                    $query->archived();
                } else {
                    $query->notArchived()->where('status', $status);
                }
            }, fn ($query) => $query->notArchived())
            ->when($due === 'soon', fn ($query) => $query->whereDate('expires_at', '<=', now()->addDays(30)))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $baseQuery = Document::where('tenant_id', $tenantId);
        $receiptInbox = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->notArchived()
            ->whereIn('receipt_status', [Document::RECEIPT_NEEDS_REVIEW, Document::RECEIPT_READY])
            ->latest('updated_at')
            ->limit(8)
            ->get();
        $folders = $this->folderOptions($tenantId);
        $folderCounts = Document::query()
            ->where('tenant_id', $tenantId)
            ->notArchived()
            ->selectRaw('folder_id, count(*) as aggregate')
            ->groupBy('folder_id')
            ->pluck('aggregate', 'folder_id');

        return view('documents.index', [
            'documents' => $documents,
            'receiptInbox' => $receiptInbox,
            'folders' => $folders,
            'folder' => $folder,
            'folderCounts' => $folderCounts,
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'due' => $due,
            'categories' => Document::categories(),
            'statuses' => Document::statuses(),
            'documentTotalCount' => (clone $baseQuery)->notArchived()->count(),
            'attentionCount' => (clone $baseQuery)->needsAttention()->count(),
            'expiringCount' => (clone $baseQuery)->notArchived()->whereDate('expires_at', '<=', now()->addDays(30))->count(),
            'archivedCount' => (clone $baseQuery)->archived()->count(),
            'receiptOpenCount' => (clone $baseQuery)->where('is_booking_receipt', true)->whereIn('receipt_status', [Document::RECEIPT_NEEDS_REVIEW, Document::RECEIPT_READY])->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('documents.create', $this->formData($request));
    }

    public function createPayable(Request $request)
    {
        return view('documents.create', $this->formData($request, receiptMode: true, payableMode: true));
    }

    public function storeFolder(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', Rule::exists('document_folders', 'id')->where('tenant_id', $tenantId)->whereNull('parent_id')],
        ]);

        $name = trim($validated['name']);
        $baseSlug = Str::slug($name) ?: 'ordner';
        $slug = $baseSlug;
        $counter = 2;

        while (DocumentFolder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('parent_id', $validated['parent_id'] ?? null)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $folder = DocumentFolder::create([
            'tenant_id' => $tenantId,
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => DocumentFolder::where('tenant_id', $tenantId)->where('parent_id', $validated['parent_id'] ?? null)->count() + 1,
        ]);

        return redirect()->route('documents.index', ['folder' => $folder->id])->with('success', 'Ordner wurde angelegt.');
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

        if ($request->input('source') === 'payables') {
            return redirect()->route('payables.index')->with('success', 'Eingangsrechnung wurde hochgeladen und zur Zahlung vorbereitet.');
        }

        return redirect()->route('documents.index')->with('success', filled($receiptData['is_booking_receipt'] ?? false)
            ? 'Beleg wurde abgelegt und in den Beleg-Eingang gelegt.'
            : 'Dokument wurde abgelegt.');
    }

    public function recognizeReceipt(Request $request, ReceiptRecognitionService $recognitionService)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $suggestions = $recognitionService->fromUpload($validated['file']);

        return response()->json([
            'recognized_amount' => filled($suggestions['recognized_amount'] ?? null)
                ? number_format((float) $suggestions['recognized_amount'], 2, '.', '')
                : null,
            'recognized_currency' => $suggestions['recognized_currency'] ?? 'EUR',
            'recognized_date' => $suggestions['recognized_date'] ?? null,
            'recognized_vendor' => $suggestions['recognized_vendor'] ?? null,
            'recognized_invoice_number' => $suggestions['recognized_invoice_number'] ?? null,
            'payable_due_date' => $suggestions['payable_due_date'] ?? null,
            'payable_due_source' => $suggestions['payable_due_source'] ?? null,
            'payable_due_note' => $suggestions['payable_due_note'] ?? null,
            'payable_iban' => $suggestions['payable_iban'] ?? null,
            'payable_reference' => $suggestions['payable_reference'] ?? null,
            'recognition_source' => $suggestions['recognition_source'] ?? null,
            'recognition_notes' => $suggestions['recognition_notes'] ?? null,
            'has_amount' => filled($suggestions['recognized_amount'] ?? null),
            'has_suggestion' => collect($suggestions)
                ->only(['recognized_amount', 'recognized_date', 'recognized_vendor', 'recognized_invoice_number'])
                ->filter(fn ($value) => filled($value))
                ->isNotEmpty(),
        ]);
    }

    public function payables(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $today = now()->startOfDay();

        $baseQuery = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->notArchived();

        $payables = (clone $baseQuery)
            ->with(['linkedTransaction'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('recognized_vendor', 'like', $like)
                        ->orWhere('recognized_invoice_number', 'like', $like)
                        ->orWhere('payable_reference', 'like', $like);
                });
            })
            ->when($status !== '', function ($query) use ($status, $today) {
                if ($status === 'overdue') {
                    $query->whereDate('payable_due_date', '<', $today)
                        ->whereNotIn('payable_status', [Document::PAYABLE_PAID, Document::PAYABLE_CANCELLED])
                        ->where('receipt_status', '!=', Document::RECEIPT_BOOKED);

                    return;
                }

                $query->where('payable_status', $status);
            })
            ->orderByRaw('payable_due_date is null')
            ->orderBy('payable_due_date')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $openCollection = (clone $baseQuery)->with('linkedTransaction')->get();
        $stats = [
            'review' => $openCollection->filter(fn (Document $document) => $document->derivedPayableStatus() === Document::PAYABLE_REVIEW)->count(),
            'open' => $openCollection->filter(fn (Document $document) => $document->derivedPayableStatus() === Document::PAYABLE_OPEN)->count(),
            'overdue' => $openCollection->filter(fn (Document $document) => $document->isPayableOverdue())->count(),
            'paid' => $openCollection->filter(fn (Document $document) => $document->derivedPayableStatus() === Document::PAYABLE_PAID)->count(),
            'open_total' => $openCollection
                ->reject(fn (Document $document) => in_array($document->derivedPayableStatus(), [Document::PAYABLE_PAID, Document::PAYABLE_CANCELLED], true))
                ->sum(fn (Document $document) => $document->payableRemainingAmount()),
        ];

        return view('documents.payables', compact('payables', 'stats', 'status', 'search'));
    }

    public function show(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        return view('documents.show', [
            'document' => $document->load(['uploader', 'member', 'project', 'event', 'protocol', 'invoice', 'folder.parent']),
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
        $receiptStatus = $document->receipt_status === Document::RECEIPT_BOOKED
            ? Document::RECEIPT_BOOKED
            : Document::RECEIPT_READY;

        $document->update($validated + [
            'is_booking_receipt' => true,
            'category' => Document::CATEGORY_FINANCE,
            'receipt_status' => $receiptStatus,
            'payable_status' => $receiptStatus === Document::RECEIPT_BOOKED
                ? $document->payable_status
                : $this->payableStatusFor($validated, Document::RECEIPT_READY),
        ]);

        return back()->with('success', 'Belegdaten wurden geprüft. Der Beleg ist jetzt buchbar.');
    }

    public function prepareTransaction(Request $request, Document $document)
    {
        $this->authorizeTenant($request, $document);

        if (! $document->is_booking_receipt) {
            return back()->with('error', 'Dieses Dokument ist nicht als Beleg markiert.');
        }

        if ($document->receipt_status === Document::RECEIPT_BOOKED || $document->linked_transaction_id) {
            return back()->with('error', 'Dieser Beleg ist bereits mit einer Buchung verknüpft und kann nicht noch einmal gebucht werden.');
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

    protected function formData(Request $request, ?bool $receiptMode = null, bool $payableMode = false): array
    {
        $tenantId = $request->user()->tenant_id;
        $receiptMode ??= $request->query('type') === 'receipt' || $request->boolean('receipt');
        $payableMode = $payableMode || $request->query('type') === 'payable' || $request->boolean('payable');

        return [
            'receiptMode' => $receiptMode,
            'payableMode' => $payableMode,
            'categories' => Document::categories(),
            'statuses' => collect(Document::statuses())->except(Document::STATUS_ARCHIVED)->all(),
            'folders' => $this->folderOptions($tenantId),
            'members' => Member::where('tenant_id', $tenantId)->notArchived()->orderBy('last_name')->orderBy('first_name')->get(),
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
            'folder_id' => ['nullable', Rule::exists('document_folders', 'id')->where('tenant_id', $tenantId)],
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

    protected function folderOptions(int|string $tenantId)
    {
        $folders = DocumentFolder::query()
            ->where('tenant_id', $tenantId)
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $folders->flatMap(function (DocumentFolder $folder) {
            return collect([$folder])->merge($folder->children);
        })->values();
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
                'payable_status' => null,
                'payable_paid_amount' => null,
                'payable_due_date' => null,
                'payable_due_source' => null,
                'payable_due_note' => null,
                'payable_iban' => null,
                'payable_reference' => null,
            ];
        }

        $suggestions = $file ? $recognitionService->fromUpload($file) : [];
        $receiptData = $this->validatedReceiptData($request, required: false);

        foreach (['recognized_amount', 'recognized_currency', 'recognized_date', 'recognized_vendor', 'recognized_invoice_number', 'payable_due_date', 'payable_due_source', 'payable_due_note', 'payable_iban', 'payable_reference'] as $field) {
            if (blank($receiptData[$field] ?? null) && filled($suggestions[$field] ?? null)) {
                $receiptData[$field] = $suggestions[$field];
            }
        }

        $receiptStatus = filled($receiptData['recognized_amount'] ?? null)
            ? Document::RECEIPT_READY
            : Document::RECEIPT_NEEDS_REVIEW;

        return $receiptData + [
            'is_booking_receipt' => true,
            'category' => Document::CATEGORY_FINANCE,
            'receipt_status' => $receiptStatus,
            'payable_status' => $this->payableStatusFor($receiptData, $receiptStatus),
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
            'payable_status' => ['nullable', Rule::in(array_keys(Document::payableStatuses()))],
            'payable_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payable_due_date' => ['nullable', 'date'],
            'payable_due_source' => ['nullable', Rule::in(['explicit', 'calculated', 'manual', 'unclear'])],
            'payable_due_note' => ['nullable', 'string', 'max:1000'],
            'payable_iban' => ['nullable', 'string', 'max:34'],
            'payable_reference' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function payableStatusFor(array $data, string $receiptStatus): string
    {
        if (($data['payable_status'] ?? null) === Document::PAYABLE_CANCELLED) {
            return Document::PAYABLE_CANCELLED;
        }

        if ($receiptStatus === Document::RECEIPT_BOOKED) {
            return Document::PAYABLE_PAID;
        }

        $amount = filled($data['recognized_amount'] ?? null) ? (float) $data['recognized_amount'] : 0.0;
        $paid = filled($data['payable_paid_amount'] ?? null) ? (float) $data['payable_paid_amount'] : 0.0;

        if ($amount > 0 && $paid >= $amount) {
            return Document::PAYABLE_PAID;
        }

        if ($paid > 0) {
            return Document::PAYABLE_PARTIAL;
        }

        return $receiptStatus === Document::RECEIPT_READY
            ? Document::PAYABLE_OPEN
            : Document::PAYABLE_REVIEW;
    }

    protected function authorizeTenant(Request $request, Document $document): void
    {
        if ((string) $document->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }
    }
}
