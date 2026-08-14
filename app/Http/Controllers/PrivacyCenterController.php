<?php

namespace App\Http\Controllers;

use App\Models\PrivacyDataExport;
use App\Models\SupportAccessGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use ZipArchive;

class PrivacyCenterController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;

        $exports = PrivacyDataExport::query()
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->take(8)
            ->get();

        $supportGrants = SupportAccessGrant::query()
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->take(8)
            ->get();

        $activeSupportGrant = $supportGrants->first(fn (SupportAccessGrant $grant) => $grant->isActive());

        $stats = [
            'exports_ready' => $exports->where('status', PrivacyDataExport::STATUS_READY)->count(),
            'support_active' => $activeSupportGrant ? 'aktiv' : 'aus',
            'subprocessors' => 1,
            'retention' => '3 Monate',
        ];

        return view('privacy.index', compact('tenant', 'exports', 'supportGrants', 'activeSupportGrant', 'stats'));
    }

    public function requestExport(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $export = PrivacyDataExport::create([
            'tenant_id' => $tenant->id,
            'requested_by' => $request->user()->id,
            'status' => PrivacyDataExport::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        try {
            $this->prepareExport($export);
        } catch (\Throwable $exception) {
            $export->update([
                'status' => PrivacyDataExport::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('privacy.index')
            ->with('success', $export->fresh()->status === PrivacyDataExport::STATUS_READY
                ? 'Datenexport wurde erstellt.'
                : 'Datenexport konnte nicht erstellt werden. Bitte pruefe das Protokoll.');
    }

    public function downloadExport(Request $request, PrivacyDataExport $privacyDataExport)
    {
        abort_unless((int) $privacyDataExport->tenant_id === (int) $request->user()->tenant_id, 404);
        abort_unless($privacyDataExport->status === PrivacyDataExport::STATUS_READY, 404);
        abort_unless($privacyDataExport->expires_at?->isFuture(), 404);
        abort_unless($privacyDataExport->path && Storage::disk($privacyDataExport->disk)->exists($privacyDataExport->path), 404);

        $privacyDataExport->forceFill(['downloaded_at' => now()])->save();

        return Storage::disk($privacyDataExport->disk)->download(
            $privacyDataExport->path,
            $privacyDataExport->filename ?: 'clubano-datenexport.zip'
        );
    }

    public function storeSupportGrant(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:metadata,documents,finance,full'],
            'duration' => ['required', 'integer', 'in:2,24,168'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        SupportAccessGrant::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
            ]);

        SupportAccessGrant::create([
            'tenant_id' => $tenant->id,
            'granted_by' => $request->user()->id,
            'scope' => $validated['scope'],
            'reason' => $validated['reason'] ?? null,
            'starts_at' => now(),
            'expires_at' => now()->addHours((int) $validated['duration']),
        ]);

        return redirect()
            ->route('privacy.index')
            ->with('success', 'Supportfreigabe wurde zeitlich begrenzt aktiviert.');
    }

    public function revokeSupportGrant(Request $request, SupportAccessGrant $supportAccessGrant): RedirectResponse
    {
        abort_unless((int) $supportAccessGrant->tenant_id === (int) $request->user()->tenant_id, 404);

        $supportAccessGrant->update([
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('privacy.index')
            ->with('success', 'Supportfreigabe wurde beendet.');
    }

    private function prepareExport(PrivacyDataExport $export): void
    {
        $tenant = $export->tenant()->withoutGlobalScopes()->firstOrFail();
        $directory = 'privacy-exports/' . $tenant->id;
        $filename = 'clubano-datenexport-' . $tenant->id . '-' . now()->format('Ymd-His') . '.zip';
        $relativePath = $directory . '/' . $filename;

        Storage::disk('local')->makeDirectory($directory);
        $absolutePath = Storage::disk('local')->path($relativePath);

        $zip = new ZipArchive();

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
        }

        $manifest = [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'exported_at' => now()->toIso8601String(),
            ],
            'note' => 'Clubano Datenexport. Strukturierte Daten werden als CSV bereitgestellt, Dokumentdateien soweit technisch vorhanden als Dateien.',
            'tables' => [],
        ];

        foreach ($this->exportTables() as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $availableColumns = collect($columns)
                ->filter(fn (string $column) => Schema::hasColumn($table, $column))
                ->values()
                ->all();

            if ($availableColumns === []) {
                continue;
            }

            $rows = DB::table($table)
                ->where('tenant_id', $tenant->id)
                ->orderBy(Schema::hasColumn($table, 'id') ? 'id' : $availableColumns[0])
                ->get($availableColumns);

            $zip->addFromString('daten/' . $table . '.csv', $this->csv($availableColumns, $rows));
            $manifest['tables'][$table] = [
                'rows' => $rows->count(),
                'columns' => $availableColumns,
            ];
        }

        $this->addDocumentFiles($zip, $tenant->id, $manifest);

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('hinweise.txt', "Dieser Export enthaelt Vereinsdaten aus Clubano.\nBitte sicher speichern und nur berechtigten Personen zugaenglich machen.\n");
        $zip->close();

        $export->update([
            'status' => PrivacyDataExport::STATUS_READY,
            'filename' => $filename,
            'disk' => 'local',
            'path' => $relativePath,
            'size' => Storage::disk('local')->size($relativePath),
            'prepared_at' => now(),
            'expires_at' => now()->addDays(14),
        ]);
    }

    private function csv(array $columns, Collection $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $columns, ';');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $column) => data_get($row, $column), $columns), ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF" . $csv;
    }

    private function addDocumentFiles(ZipArchive $zip, int $tenantId, array &$manifest): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        $documents = DB::table('documents')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('path')
            ->get(['id', 'disk', 'path', 'original_name']);

        $added = 0;

        foreach ($documents as $document) {
            $disk = $document->disk ?: 'local';

            if (! Storage::disk($disk)->exists($document->path)) {
                continue;
            }

            $name = $document->original_name ?: basename($document->path);
            $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: ('dokument-' . $document->id);
            $zip->addFile(Storage::disk($disk)->path($document->path), 'dokumente/' . $document->id . '-' . $safeName);
            $added++;
        }

        $manifest['document_files'] = $added;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function exportTables(): array
    {
        return [
            'tenants' => ['id', 'name', 'email', 'address', 'zip', 'city', 'phone', 'register_number', 'created_at', 'updated_at'],
            'users' => ['id', 'name', 'email', 'role', 'last_login_at', 'email_verified_at', 'created_at', 'updated_at'],
            'members' => ['id', 'member_id', 'first_name', 'last_name', 'organization', 'email', 'phone', 'mobile', 'address', 'zip', 'city', 'birthdate', 'entry_date', 'exit_date', 'status', 'membership_id', 'iban', 'created_at', 'updated_at'],
            'contacts' => ['id', 'contact_type', 'category', 'organization', 'first_name', 'last_name', 'email', 'primary_email', 'phone', 'mobile', 'address', 'zip', 'city', 'created_at', 'updated_at'],
            'memberships' => ['id', 'name', 'amount', 'interval', 'created_at', 'updated_at'],
            'accounts' => ['id', 'number', 'name', 'type', 'category', 'opening_balance', 'current_balance', 'is_active', 'created_at', 'updated_at'],
            'transactions' => ['id', 'date', 'description', 'amount', 'account_id', 'account_from_id', 'account_to_id', 'receipt_number', 'status', 'created_at', 'updated_at'],
            'invoices' => ['id', 'invoice_number', 'recipient_name', 'recipient_email', 'invoice_date', 'due_date', 'total', 'status', 'created_at', 'updated_at'],
            'invoice_items' => ['id', 'invoice_id', 'description', 'quantity', 'unit_price', 'total', 'created_at', 'updated_at'],
            'payments' => ['id', 'invoice_id', 'amount', 'payment_date', 'payment_method', 'created_at', 'updated_at'],
            'sepa_runs' => ['id', 'filename', 'total_amount', 'status', 'created_at', 'updated_at'],
            'documents' => ['id', 'title', 'category', 'status', 'description', 'original_name', 'mime_type', 'size', 'member_id', 'project_id', 'event_id', 'protocol_id', 'invoice_id', 'created_at', 'updated_at'],
            'protocols' => ['id', 'title', 'type', 'location', 'meeting_date', 'start_time', 'end_time', 'status', 'created_at', 'updated_at'],
            'protocol_entries' => ['id', 'protocol_id', 'type', 'agenda_title', 'title', 'content', 'assignee', 'due_date', 'event_date', 'sort_order', 'created_at', 'updated_at'],
            'events' => ['id', 'title', 'description', 'location', 'start', 'end', 'category_id', 'target_group', 'bookable', 'created_at', 'updated_at'],
            'event_invitations' => ['id', 'event_id', 'member_id', 'email', 'status', 'responded_at', 'created_at', 'updated_at'],
            'event_bookings' => ['id', 'event_id', 'booker_name', 'booker_email', 'booker_phone', 'participant_count', 'status', 'payment_status', 'created_at', 'updated_at'],
            'event_booking_participants' => ['id', 'event_booking_id', 'member_id', 'contact_id', 'first_name', 'last_name', 'organization_name', 'email', 'phone', 'price', 'payment_status', 'created_at', 'updated_at'],
            'templates' => ['id', 'name', 'type', 'subject', 'created_at', 'updated_at'],
            'template_dispatch_logs' => ['id', 'template_id', 'channel', 'recipient_type', 'recipient_reference', 'subject', 'dispatched_at', 'created_at', 'updated_at'],
            'import_runs' => ['id', 'import_type', 'created_by', 'filename', 'status', 'row_count', 'imported_count', 'skipped_count', 'created_at', 'updated_at'],
        ];
    }
}
