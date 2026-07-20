<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SepaRun;
use App\Models\SepaRunItem;
use App\Services\SepaExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SepaExportController extends Controller
{
    public function __construct(private readonly SepaExportService $sepaExportService)
    {
    }

    public function create()
    {
        $tenant = auth()->user()->tenant;
        $eligibleInvoices = $this->eligibleInvoices();

        return view('sepa.create', [
            'tenant' => $tenant,
            'eligibleInvoices' => $eligibleInvoices,
            'collectionDate' => now()->addDays(5)->toDateString(),
            'totalAmount' => $eligibleInvoices->sum(fn (Invoice $invoice) => $invoice->getTotal()),
            'recentRuns' => SepaRun::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->with(['creator', 'items'])
                ->latest('exported_at')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tenant = auth()->user()->tenant;
        $validated = $request->validate([
            'collection_date' => ['required', 'date', 'after_or_equal:today'],
            'sequence_type' => ['required', 'in:OOFF,FRST,RCUR,FNAL'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer'],
        ]);

        $eligibleInvoices = $this->eligibleInvoices()
            ->whereIn('id', $validated['invoice_ids'])
            ->values();

        abort_if($eligibleInvoices->isEmpty(), 422, 'Es wurden keine gueltigen SEPA-Rechnungen ausgewaehlt.');

        $xml = $this->sepaExportService->generatePain008(
            $tenant,
            $eligibleInvoices,
            Carbon::parse($validated['collection_date']),
            $validated['sequence_type']
        );

        $filename = 'sepa-lastschriftlauf-' . now()->format('Ymd-His') . '.xml';
        $filePath = 'sepa/' . auth()->user()->tenant_id . '/' . $filename;

        $run = SepaRun::create([
            'tenant_id' => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
            'sequence_type' => $validated['sequence_type'],
            'collection_date' => $validated['collection_date'],
            'transaction_count' => $eligibleInvoices->count(),
            'control_sum' => $eligibleInvoices->sum(fn (Invoice $invoice) => $invoice->getTotal()),
            'file_name' => $filename,
            'file_path' => $filePath,
            'exported_at' => now(),
        ]);

        Storage::disk('local')->put($filePath, $xml);

        foreach ($eligibleInvoices as $invoice) {
            SepaRunItem::create([
                'sepa_run_id' => $run->id,
                'invoice_id' => $invoice->id,
                'member_id' => $invoice->member?->id,
                'invoice_number' => $invoice->invoice_number,
                'member_name' => $invoice->member?->full_name,
                'mandate_reference' => $invoice->member?->sepa_mandate_reference,
                'amount' => $invoice->getTotal(),
            ]);

            $invoice->forceFill([
                'sepa_exported_at' => now(),
                'sepa_sequence_type' => $validated['sequence_type'],
                'last_sepa_run_id' => $run->id,
            ])->save();
        }

        return response()->streamDownload(
            static function () use ($xml): void {
                echo $xml;
            },
            $filename,
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );
    }

    public function download(SepaRun $sepaRun): StreamedResponse
    {
        abort_if($sepaRun->tenant_id !== auth()->user()->tenant_id, 403);
        abort_unless($sepaRun->file_path && Storage::disk('local')->exists($sepaRun->file_path), 404);

        return Storage::disk('local')->download($sepaRun->file_path, $sepaRun->file_name ?: basename($sepaRun->file_path));
    }

    private function eligibleInvoices()
    {
        return Invoice::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'open')
            ->whereNotNull('member_id')
            ->with(['member', 'items'])
            ->get()
            ->filter(function (Invoice $invoice) {
                $member = $invoice->member;

                return $member
                    && $member->payment_method === 'sepa_lastschrift'
                    && filled($member->iban)
                    && filled($member->bic)
                    && filled($member->sepa_mandate_reference)
                    && filled($member->sepa_signed_at)
                    && $invoice->getTotal() > 0;
            })
            ->values();
    }
}
