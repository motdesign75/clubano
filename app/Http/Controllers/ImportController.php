<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CustomMemberField;
use App\Models\ImportRun;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Tag;
use App\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    public function index()
    {
        $recentImportRuns = $this->recentImportRuns();
        $qualitySummaries = $recentImportRuns
            ->mapWithKeys(fn (ImportRun $run) => [$run->id => $this->qualitySummaryFor($run)])
            ->all();

        return view('import.index', compact('recentImportRuns', 'qualitySummaries'));
    }

    public function showUploadForm()
    {
        return $this->showTypedUploadForm('members');
    }

    public function showContactUploadForm()
    {
        return $this->showTypedUploadForm('contacts');
    }

    public function preview(Request $request)
    {
        return $this->previewTypedImport($request, 'members');
    }

    public function previewContacts(Request $request)
    {
        return $this->previewTypedImport($request, 'contacts');
    }

    public function confirm(Request $request)
    {
        return $this->confirmTypedImport($request, 'members');
    }

    public function confirmContacts(Request $request)
    {
        return $this->confirmTypedImport($request, 'contacts');
    }

    public function undo(ImportRun $importRun)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        if (! $importRun->isUndoable()) {
            return $this->redirectToImport($importRun->import_type)
                ->with('error', 'Dieser Import kann nicht mehr rueckgaengig gemacht werden.');
        }

        if ($importRun->import_type === 'contacts') {
            return $this->undoContactsImport($importRun);
        }

        return $this->undoMembersImport($importRun);
    }

    public function report(ImportRun $importRun)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        return view('import.report', [
            'importRun' => $importRun->load('creator'),
            'config' => $this->importConfig($importRun->import_type),
            'nextSteps' => $this->nextStepsFor($importRun),
            'qualityChecks' => $this->qualityChecksFor($importRun),
            'releaseChecks' => $this->releaseChecksFor($importRun),
        ]);
    }

    public function qualityIssue(Request $request, ImportRun $importRun, string $issue)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        $check = collect($this->qualityChecksFor($importRun))->firstWhere('key', $issue);
        abort_unless($check, 404);

        $search = trim((string) $request->query('q', ''));

        $records = $this->qualityIssueQuery($importRun, $issue, $search)
            ->paginate(25)
            ->withQueryString();

        return view('import.quality-issue', [
            'importRun' => $importRun,
            'config' => $this->importConfig($importRun->import_type),
            'check' => $check,
            'records' => $records,
            'search' => $search,
        ]);
    }

    public function qualityIssueExport(Request $request, ImportRun $importRun, string $issue)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        $check = collect($this->qualityChecksFor($importRun))->firstWhere('key', $issue);
        abort_unless($check, 404);

        $search = trim((string) $request->query('q', ''));
        $spreadsheet = $this->buildQualityIssueSpreadsheet($importRun, $issue, $check, $search);
        $filename = 'clubano-korrekturliste-' . $issue . '-' . $importRun->id . '.xlsx';

        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    public function template(string $type)
    {
        abort_unless(in_array($type, ['mitglieder', 'kontakte'], true), 404);

        $importType = $type === 'kontakte' ? 'contacts' : 'members';
        $spreadsheet = $this->buildTemplateSpreadsheet($importType);
        $filename = 'clubano-importvorlage-' . $type . '.xlsx';

        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    public function reportExport(ImportRun $importRun)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        $spreadsheet = $this->buildReportSpreadsheet($importRun);
        $filename = 'clubano-importbericht-' . $importRun->id . '.xlsx';

        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    public function correctionsExport(ImportRun $importRun)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        $spreadsheet = $this->buildCorrectionsWorkbook($importRun);
        $filename = 'clubano-korrekturmappe-import-' . $importRun->id . '.xlsx';

        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    private function showTypedUploadForm(string $type)
    {
        $recentImportRuns = $this->recentImportRuns($type);
        $config = $this->importConfig($type);

        return view('import.upload', compact('recentImportRuns', 'config'));
    }

    private function previewTypedImport(Request $request, string $type)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx,xls',
            'source_profile' => 'nullable|string|max:60',
            'import_goal' => 'nullable|string|max:120',
        ]);

        $path = $request->file('csv_file')->store('temp');
        $originalFilename = $request->file('csv_file')->getClientOriginalName();
        $parsed = $this->readTabularFileFromStorage($path, $originalFilename);
        $config = $this->importConfig($type);
        $fieldOptions = $this->fieldOptions($type);
        $suggestedMapping = $this->suggestMapping($parsed['headers'], $type);
        $previewRows = array_slice($parsed['rows'], 0, 8);
        $analysis = $this->analyzeRows($parsed['rows'], $suggestedMapping, $type);
        $sourceProfile = $this->normalizeSourceProfile($request->input('source_profile'));
        $importGoal = trim((string) $request->input('import_goal'));
        $duplicateAnalysis = $this->analyzeDuplicates($parsed['rows'], $suggestedMapping, $type, auth()->user()->tenant_id);

        return view('import.preview', [
            'config' => $config,
            'path' => $path,
            'originalFilename' => $originalFilename,
            'sourceProfile' => $sourceProfile,
            'sourceProfileLabel' => $this->sourceProfileLabel($sourceProfile),
            'importGoal' => $importGoal,
            'headers' => $parsed['headers'],
            'rows' => $previewRows,
            'delimiter' => $parsed['delimiter'],
            'fileType' => $parsed['file_type'],
            'rowCount' => count($parsed['rows']),
            'fieldOptions' => $fieldOptions,
            'suggestedMapping' => $suggestedMapping,
            'analysis' => $analysis,
            'duplicateAnalysis' => $duplicateAnalysis,
            'readiness' => $this->buildReadiness($parsed['headers'], $parsed['rows'], $suggestedMapping, $type),
        ]);
    }

    private function confirmTypedImport(Request $request, string $type)
    {
        $request->validate([
            'path' => 'required|string',
            'mapping' => 'required|array',
            'source_profile' => 'nullable|string|max:60',
            'original_filename' => 'nullable|string|max:255',
            'import_goal' => 'nullable|string|max:120',
            'duplicate_strategy' => 'nullable|in:skip,create_new',
            'membership_strategy' => 'nullable|in:snapshot_only,create_and_assign',
            'custom_field_strategy' => 'nullable|in:ignore,create_from_unmapped',
        ]);

        $parsed = $this->readTabularFileFromStorage($request->input('path'), $request->input('original_filename'));
        $rows = $parsed['rows'];
        $mapping = $request->input('mapping');
        $tenantId = auth()->user()->tenant_id;
        $importedCount = 0;
        $skippedRows = [];
        $createdMembershipIds = [];
        $createdCustomFieldIds = [];
        $importRun = null;
        $sourceProfile = $this->normalizeSourceProfile($request->input('source_profile'));
        $duplicateStrategy = $request->input('duplicate_strategy', 'skip');
        $membershipStrategy = $request->input('membership_strategy', 'snapshot_only');
        $customFieldStrategy = $request->input('custom_field_strategy', 'ignore');
        $duplicateAnalysisBeforeImport = $this->analyzeDuplicates($rows, $mapping, $type, $tenantId);
        $mappingBlocker = $this->mappingBlockerMessage($mapping, $type);

        if ($mappingBlocker) {
            return $this->redirectToImport($type)->with('error', $mappingBlocker);
        }

        if ($type === 'members' && $limitMessage = $this->memberImportLimitMessage($rows, $mapping, $tenantId, $duplicateStrategy)) {
            return redirect()
                ->route('import.mitglieder')
                ->with('error', $limitMessage);
        }

        DB::transaction(function () use ($type, $request, $rows, $mapping, $tenantId, $parsed, $sourceProfile, $duplicateStrategy, $membershipStrategy, $customFieldStrategy, $duplicateAnalysisBeforeImport, &$importRun, &$importedCount, &$skippedRows, &$createdMembershipIds, &$createdCustomFieldIds) {
            $importRun = ImportRun::create([
                'tenant_id' => $tenantId,
                'import_type' => $type,
                'created_by' => auth()->id(),
                'filename' => $request->input('original_filename') ?: basename((string) $request->input('path')),
                'status' => 'completed',
                'row_count' => count($rows),
                'imported_count' => 0,
                'skipped_count' => 0,
            ]);
            $customFieldMap = $type === 'members'
                ? $this->resolveCustomFieldMap($parsed['headers'], $rows, $mapping, $tenantId, $customFieldStrategy, $createdCustomFieldIds)
                : [];

            foreach ($rows as $position => $row) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->normalizeMappedRow($row, $mapping, $type);
                $validationError = $this->rowValidationError($data, $type);

                if ($validationError) {
                    $skippedRows[] = $this->skippedRowPayload($position, $validationError, $data, $type);
                    continue;
                }

                if ($duplicateStrategy === 'skip' && $this->isDuplicate($data, $type, $tenantId)) {
                    $skippedRows[] = $this->skippedRowPayload($position, 'Mögliche Dublette vorhanden', $data, $type);
                    continue;
                }

                $data['tenant_id'] = $tenantId;
                $data['import_run_id'] = $importRun->id;

                if ($type === 'contacts') {
                    Contact::create($this->prepareContactData($data));
                } else {
                    $tags = $data['tags'] ?? [];
                    unset($data['tags']);
                    $member = Member::create($this->prepareMemberData($data, $tenantId, $membershipStrategy, $createdMembershipIds));
                    $this->attachImportedTags($member, $tags, $tenantId);
                    $this->storeImportedCustomValues($member, $row, $customFieldMap);
                }

                $importedCount++;
            }

            $importRun->forceFill([
                'imported_count' => $importedCount,
                'skipped_count' => count($skippedRows),
                'summary' => [
                    'source_profile' => $sourceProfile,
                    'source_profile_label' => $this->sourceProfileLabel($sourceProfile),
                    'import_goal' => trim((string) $request->input('import_goal')),
                    'delimiter' => $parsed['delimiter'],
                    'file_type' => $parsed['file_type'],
                    'duplicate_strategy' => $duplicateStrategy,
                    'duplicate_strategy_label' => $this->duplicateStrategyLabel($duplicateStrategy),
                    'duplicate_count' => $duplicateAnalysisBeforeImport['duplicate_count'],
                    'membership_strategy' => $type === 'members' ? $membershipStrategy : null,
                    'membership_strategy_label' => $type === 'members' ? $this->membershipStrategyLabel($membershipStrategy) : null,
                    'created_membership_ids' => array_values(array_unique($createdMembershipIds)),
                    'created_membership_count' => count(array_unique($createdMembershipIds)),
                    'custom_field_strategy' => $type === 'members' ? $customFieldStrategy : null,
                    'custom_field_strategy_label' => $type === 'members' ? $this->customFieldStrategyLabel($customFieldStrategy) : null,
                    'created_custom_field_ids' => array_values(array_unique($createdCustomFieldIds)),
                    'created_custom_field_count' => count(array_unique($createdCustomFieldIds)),
                    'mapped_fields' => $this->mappedFieldLabels($mapping, $type),
                    'readiness' => $this->buildReadiness($parsed['headers'], $rows, $mapping, $type),
                    'skipped_rows' => array_slice($skippedRows, 0, 50),
                ],
            ])->save();
        });

        $message = $type === 'contacts'
            ? 'Kontakte importiert.'
            : 'Mitglieder importiert.';

        if (count($skippedRows) > 0) {
            $message .= ' ' . count($skippedRows) . ' Zeile(n) wurden wegen Fehlern oder Dubletten uebersprungen.';
        }

        return redirect()->route('import.report', $importRun)->with('success', $message);
    }

    private function undoMembersImport(ImportRun $importRun)
    {
        $members = $importRun->members()
            ->withCount(['invoices', 'publicFormSubmissions', 'communicationLogs', 'credits', 'protocols'])
            ->get();

        $blockedMembers = $members->filter(function (Member $member) {
            return $member->invoices_count > 0
                || $member->public_form_submissions_count > 0
                || $member->communication_logs_count > 0
                || $member->credits_count > 0
                || $member->protocols_count > 0;
        });

        if ($blockedMembers->isNotEmpty()) {
            return $this->redirectToImport('members')
                ->with('error', 'Der Import kann nicht mehr automatisch rueckgaengig gemacht werden, weil bereits Folgeaktionen an einzelnen Mitgliedern haengen.');
        }

        DB::transaction(function () use ($members, $importRun) {
            foreach ($members as $member) {
                $member->tags()->detach();
                $member->customValues()->delete();
                $member->delete();
            }

            $this->deleteUnusedImportedMemberships($importRun);
            $this->deleteUnusedImportedCustomFields($importRun);
            $this->markImportUndone($importRun);
        });

        return $this->redirectToImport('members')->with('success', 'Der Import wurde rueckgaengig gemacht.');
    }

    private function undoContactsImport(ImportRun $importRun)
    {
        $contacts = $importRun->contacts()->get();

        DB::transaction(function () use ($contacts, $importRun) {
            foreach ($contacts as $contact) {
                $contact->delete();
            }

            $this->markImportUndone($importRun);
        });

        return $this->redirectToImport('contacts')->with('success', 'Der Import wurde rueckgaengig gemacht.');
    }

    private function markImportUndone(ImportRun $importRun): void
    {
        $importRun->forceFill([
            'status' => 'undone',
            'undone_at' => now(),
            'imported_count' => 0,
        ])->save();
    }

    private function readTabularFileFromStorage(string $path, ?string $originalFilename = null): array
    {
        $extension = Str::lower(pathinfo($originalFilename ?: $path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->readSpreadsheetFromStorage($path, $extension);
        }

        return $this->readCsvFromStorage($path);
    }

    private function readCsvFromStorage(string $path): array
    {
        $content = Storage::get($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

            if ($encoding && $encoding !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }
        }

        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $lines = array_values(array_filter($lines, fn ($line) => trim((string) $line) !== ''));
        $delimiter = $this->detectDelimiter($lines[0] ?? '');
        $rows = array_map(fn ($line) => str_getcsv($line, $delimiter), $lines);
        $headers = array_map(fn ($header) => trim((string) $header), $rows[0] ?? []);

        return [
            'headers' => $headers,
            'rows' => array_values(array_filter(array_slice($rows, 1), fn ($row) => ! $this->isEmptyRow($row))),
            'delimiter' => $delimiter,
            'file_type' => 'csv',
        ];
    }

    private function readSpreadsheetFromStorage(string $path, string $extension): array
    {
        $spreadsheet = IOFactory::load(Storage::path($path));
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        $rows = [];

        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            $row = [];

            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $cell = $worksheet->getCell([$columnIndex, $rowIndex]);
                $row[] = trim((string) $cell->getFormattedValue());
            }

            if (! $this->isEmptyRow($row)) {
                $rows[] = $row;
            }
        }

        $headers = array_map(fn ($header) => trim((string) $header), $rows[0] ?? []);

        return [
            'headers' => $headers,
            'rows' => array_values(array_filter(array_slice($rows, 1), fn ($row) => ! $this->isEmptyRow($row))),
            'delimiter' => null,
            'file_type' => $extension,
        ];
    }

    private function detectDelimiter(string $headerLine): string
    {
        $delimiters = [';' => 0, ',' => 0, "\t" => 0, '|' => 0];

        foreach ($delimiters as $delimiter => $count) {
            $delimiters[$delimiter] = count(str_getcsv($headerLine, $delimiter));
        }

        arsort($delimiters);

        return array_key_first($delimiters) ?: ';';
    }

    private function normalizeMappedRow(array $row, array $mapping, string $type): array
    {
        $data = [];

        foreach ($mapping as $index => $field) {
            if ($field === 'skip' || ! isset($row[$index])) {
                continue;
            }

            $field = $this->normalizeFieldName((string) $field, $type);
            $value = $this->normalizeValue($field, $row[$index], $type);

            if ($field === 'tags' && $value !== null) {
                $data[$field] = array_values(array_merge($data[$field] ?? [], $value));
            } elseif ($value !== null) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    private function normalizeFieldName(string $field, string $type): string
    {
        if ($type === 'contacts') {
            return match ($field) {
                'company' => 'organization',
                'phone_mobile' => 'mobile',
                'phone_landline' => 'phone',
                'postal_code' => 'zip',
                'street_addition' => 'address_addition',
                default => $field,
            };
        }

        return match ($field) {
            'company' => 'organization',
            'phone' => 'landline',
            'address_extra' => 'address_addition',
            'co' => 'care_of',
            default => $field,
        };
    }

    private function normalizeValue(string $field, mixed $value, string $type): mixed
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($field === 'gender') {
            $normalized = Str::lower($value);
            $allowed = ['männlich', 'weiblich', 'divers'];

            return in_array($normalized, $allowed, true) ? $normalized : null;
        }

        if ($field === 'salutation') {
            $allowed = $type === 'contacts'
                ? ['Herr', 'Frau', 'Divers', 'Liebe', 'Lieber', 'Hallo']
                : ['Herr', 'Frau', 'Liebe', 'Lieber', 'Hallo'];

            return in_array($value, $allowed, true) ? $value : null;
        }

        if (in_array($field, ['birthday', 'entry_date', 'exit_date', 'cancellation_date', 'termination_date', 'sepa_signed_at', 'consent_given_at', 'last_contacted_at', 'gdpr_consent_at'], true)) {
            return $this->normalizeDate($value);
        }

        if (in_array($field, ['membership_amount', 'required_service_hours'], true)) {
            return $this->normalizeDecimal($value);
        }

        if ($field === 'membership_interval') {
            return $this->canonicalMembershipInterval($value);
        }

        if ($field === 'payment_method') {
            return $this->canonicalPaymentMethod($value);
        }

        if (in_array($field, ['is_active', 'is_favorite', 'consent_email', 'consent_phone', 'consent_post', 'gdpr_consent'], true)) {
            return in_array(Str::lower($value), ['1', 'ja', 'yes', 'true', 'x'], true);
        }

        if ($field === 'contact_type') {
            $normalized = Str::lower($value);

            return match ($normalized) {
                'organisation', 'organization', 'firma', 'unternehmen', 'verein', 'behoerde', 'behörde' => 'organization',
                'person', 'ansprechpartner', 'kontaktperson', 'privatperson' => 'person',
                default => in_array($normalized, ['person', 'organization'], true) ? $normalized : 'person',
            };
        }

        if ($type === 'contacts' && $field === 'category') {
            return $this->canonicalContactCategory($value);
        }

        if ($field === 'tags') {
            return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $value) ?: [])));
        }

        return $value;
    }

    private function canonicalMembershipInterval(string $interval): string
    {
        return match (trim(Str::lower($interval))) {
            'monatlich', 'monat' => 'monatlich',
            'vierteljaehrlich', 'vierteljährlich', 'quartal', 'quartalsweise', 'quartalsweise zum 15.', 'vierteljährig' => 'vierteljährlich',
            'halbjaehrlich', 'halbjährlich', 'halbjahr', 'halbjährig' => 'halbjährlich',
            'jaehrlich', 'jährlich', 'jahr', 'einmal jährlich' => 'jährlich',
            default => trim($interval),
        };
    }

    private function canonicalPaymentMethod(string $paymentMethod): string
    {
        return match (trim(Str::lower($paymentMethod))) {
            'sepa', 'lastschrift', 'sepa-lastschrift', 'sepalastschrift', 'bankeinzug', 'einzug' => 'sepa_lastschrift',
            'ueberweisung', 'überweisung', 'rechnung' => 'ueberweisung',
            'bar', 'kasse' => 'bar',
            default => trim($paymentMethod),
        };
    }

    private function canonicalContactCategory(string $category): string
    {
        return match (trim(Str::lower($category))) {
            'sponsor', 'sponsoren', 'sponsoring', 'werbepartner' => 'sponsor',
            'lieferant', 'lieferanten', 'zulieferer' => 'supplier',
            'partner', 'kooperationspartner', 'kooperation' => 'partner',
            'presse', 'medien', 'zeitung' => 'press',
            'behoerde', 'behörde', 'amt', 'verwaltung', 'stadtverwaltung', 'stadt', 'gemeinde', 'landkreis', 'kommune', 'kommunalverwaltung' => 'authority',
            'trainer', 'trainerin', 'uebungsleiter', 'übungsleiter', 'coach' => 'trainer',
            'eltern', 'elternteil', 'mutter', 'vater', 'erziehungsberechtigte' => 'parent',
            'ehrenamt', 'ehrenamtlich', 'helfer', 'freiwillige' => 'volunteer',
            'spender', 'spenderin', 'zuwender', 'donator' => 'donor',
            'dienstleister', 'service', 'agentur', 'handwerker' => 'service',
            'sonstiges', 'sonstige', 'andere', 'other' => 'other',
            default => trim($category),
        };
    }

    private function normalizeDecimal(string $value): ?string
    {
        $normalized = str_replace(['.', ' '], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? number_format((float) $normalized, 2, '.', '') : null;
    }

    private function normalizeDate(string $value): ?string
    {
        foreach (['d.m.Y', 'd.m.y', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function rowValidationError(array $data, string $type): ?string
    {
        if ($type === 'contacts') {
            if (blank($data['organization'] ?? null) && blank($data['first_name'] ?? null) && blank($data['last_name'] ?? null)) {
                return 'Kontakt ohne Name oder Organisation';
            }

            if (filled($data['email'] ?? null) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return 'Ungültige E-Mail-Adresse';
            }

            return null;
        }

        if (blank($data['first_name'] ?? null) || blank($data['last_name'] ?? null)) {
            return 'Mitglied ohne vollständigen Vor- und Nachnamen';
        }

        if (filled($data['email'] ?? null) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Ungültige E-Mail-Adresse';
        }

        return null;
    }

    private function isDuplicate(array $data, string $type, int $tenantId): bool
    {
        if ($type === 'contacts') {
            if (filled($data['email'] ?? null) && Contact::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('email', $data['email'])->exists()) {
                return true;
            }

            if (filled($data['organization'] ?? null) && blank($data['email'] ?? null)) {
                return Contact::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('organization', $data['organization'])
                    ->where('city', $data['city'] ?? null)
                    ->exists();
            }

            return false;
        }

        if (filled($data['member_id'] ?? null) && Member::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('member_id', $data['member_id'])->exists()) {
            return true;
        }

        if (filled($data['email'] ?? null) && Member::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('email', $data['email'])->exists()) {
            return true;
        }

        return filled($data['first_name'] ?? null)
            && filled($data['last_name'] ?? null)
            && Member::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->when(filled($data['birthday'] ?? null), fn ($query) => $query->where('birthday', $data['birthday']))
                ->exists();
    }

    private function prepareContactData(array $data): array
    {
        if (blank($data['contact_type'] ?? null) || (($data['contact_type'] ?? null) === 'person' && filled($data['organization'] ?? null) && blank($data['first_name'] ?? null) && blank($data['last_name'] ?? null))) {
            $data['contact_type'] = filled($data['organization'] ?? null) && blank($data['first_name'] ?? null) && blank($data['last_name'] ?? null) ? 'organization' : 'person';
        }
        $data['category'] = filled($data['category'] ?? null) ? $this->canonicalContactCategory((string) $data['category']) : null;
        $data['country'] = $data['country'] ?? 'Deutschland';
        $data['is_active'] = $data['is_active'] ?? true;
        $data['company'] = $data['company'] ?? ($data['organization'] ?? null);
        $data['phone_mobile'] = $data['phone_mobile'] ?? ($data['mobile'] ?? null);
        $data['phone_landline'] = $data['phone_landline'] ?? ($data['phone'] ?? null);
        $data['postal_code'] = $data['postal_code'] ?? ($data['zip'] ?? null);
        $data['street_addition'] = $data['street_addition'] ?? ($data['address_addition'] ?? null);
        $data['status'] = $data['status'] ?? 'aktiv';

        return $data;
    }

    private function prepareMemberData(array $data, int $tenantId, string $membershipStrategy, array &$createdMembershipIds): array
    {
        $membershipName = trim((string) ($data['membership_name'] ?? ''));
        unset($data['membership_name']);

        if ($membershipStrategy !== 'create_and_assign') {
            return $data;
        }

        $amount = $data['membership_amount'] ?? null;
        $interval = $data['membership_interval'] ?? null;

        if ($membershipName === '' && blank($amount)) {
            return $data;
        }

        [$membership, $created] = $this->resolveImportedMembership($tenantId, $membershipName, $amount, $interval);

        if (! $membership) {
            return $data;
        }

        if ($created) {
            $createdMembershipIds[] = $membership->id;
        }

        $data['membership_id'] = $membership->id;
        $data['membership_amount'] = $membership->amount;
        $data['membership_interval'] = $membership->interval;

        return $data;
    }

    private function resolveImportedMembership(int $tenantId, string $membershipName, mixed $amount, ?string $interval): array
    {
        $normalizedAmount = filled($amount) ? number_format((float) $amount, 2, '.', '') : '0.00';
        $normalizedInterval = $this->validMembershipInterval($this->canonicalMembershipInterval($interval ?: 'jährlich'));
        $name = $membershipName !== ''
            ? $membershipName
            : 'Importbeitrag ' . number_format((float) $normalizedAmount, 2, ',', '.') . ' EUR / ' . $normalizedInterval;

        $existingByName = Membership::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->first();

        if ($existingByName) {
            return [$existingByName, false];
        }

        $existingByTerms = Membership::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('amount', $normalizedAmount)
            ->where('interval', $normalizedInterval)
            ->first();

        if ($existingByTerms && $membershipName === '') {
            return [$existingByTerms, false];
        }

        $membership = Membership::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'amount' => $normalizedAmount,
            'interval' => $normalizedInterval,
        ]);

        return [$membership, true];
    }

    private function validMembershipInterval(string $interval): string
    {
        return in_array($interval, ['monatlich', 'vierteljährlich', 'halbjährlich', 'jährlich'], true)
            ? $interval
            : 'jährlich';
    }

    private function deleteUnusedImportedMemberships(ImportRun $importRun): void
    {
        $membershipIds = array_filter($importRun->summary['created_membership_ids'] ?? []);

        if ($membershipIds === []) {
            return;
        }

        Membership::withoutGlobalScopes()
            ->where('tenant_id', $importRun->tenant_id)
            ->whereIn('id', $membershipIds)
            ->get()
            ->each(function (Membership $membership) {
                if ($membership->members()->withoutGlobalScopes()->count() === 0) {
                    $membership->delete();
                }
            });
    }

    private function attachImportedTags(Member $member, array $tagNames, int $tenantId): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($tagName) => trim((string) $tagName))
            ->filter()
            ->unique(fn ($tagName) => Str::lower($tagName))
            ->map(function (string $tagName) use ($tenantId) {
                return Tag::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => $tagName,
                    ],
                    [
                        'color' => '#2954A3',
                    ]
                )->id;
            })
            ->values()
            ->all();

        if ($tagIds !== []) {
            $member->tags()->syncWithoutDetaching($tagIds);
        }
    }

    private function resolveCustomFieldMap(array $headers, array $rows, array $mapping, int $tenantId, string $customFieldStrategy, array &$createdCustomFieldIds): array
    {
        if ($customFieldStrategy !== 'create_from_unmapped') {
            return [];
        }

        $maxOrder = (int) CustomMemberField::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->max('order');
        $fieldMap = [];

        foreach ($headers as $index => $header) {
            if (($mapping[$index] ?? 'skip') !== 'skip') {
                continue;
            }

            $label = trim((string) $header);

            if ($label === '') {
                continue;
            }

            $hasValues = collect($rows)->contains(fn ($row) => trim((string) ($row[$index] ?? '')) !== '');

            if (! $hasValues) {
                continue;
            }

            $slug = Str::slug($label) ?: 'importfeld-' . ($index + 1);
            $field = CustomMemberField::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->first();

            if (! $field) {
                $field = CustomMemberField::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'name' => $slug,
                    'label' => $label,
                    'slug' => $slug,
                    'type' => 'text',
                    'required' => false,
                    'visible' => true,
                    'order' => ++$maxOrder,
                ]);
                $createdCustomFieldIds[] = $field->id;
            }

            $fieldMap[$index] = $field;
        }

        return $fieldMap;
    }

    private function storeImportedCustomValues(Member $member, array $row, array $customFieldMap): void
    {
        foreach ($customFieldMap as $index => $field) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($value === '') {
                continue;
            }

            $member->customValues()->create([
                'custom_member_field_id' => $field->id,
                'value' => $value,
            ]);
        }
    }

    private function deleteUnusedImportedCustomFields(ImportRun $importRun): void
    {
        $fieldIds = array_filter($importRun->summary['created_custom_field_ids'] ?? []);

        if ($fieldIds === []) {
            return;
        }

        CustomMemberField::withoutGlobalScopes()
            ->where('tenant_id', $importRun->tenant_id)
            ->whereIn('id', $fieldIds)
            ->get()
            ->each(function (CustomMemberField $field) {
                if ($field->values()->count() === 0) {
                    $field->delete();
                }
            });
    }

    private function analyzeRows(array $rows, array $mapping, string $type): array
    {
        $warnings = [];
        $validRows = 0;

        foreach ($rows as $position => $row) {
            $data = $this->normalizeMappedRow($row, $mapping, $type);
            $error = $this->rowValidationError($data, $type);

            if ($error) {
                $warnings[] = [
                    'row' => $position + 2,
                    'reason' => $error,
                ];
            } else {
                $validRows++;
            }
        }

        return [
            'valid_rows' => $validRows,
            'warning_count' => count($warnings),
            'warnings' => array_slice($warnings, 0, 10),
        ];
    }

    private function memberImportLimitMessage(array $rows, array $mapping, int $tenantId, string $duplicateStrategy = 'skip'): ?string
    {
        $tenant = auth()->user()?->tenant;

        if (! $tenant || $tenant->hasComplimentaryAccess() || ! $tenant->subscribed('default')) {
            return null;
        }

        $limit = app(LicenseService::class)->resolvePlan($tenant)['member_limit'] ?? null;

        if ($limit === null) {
            return null;
        }

        $currentMembers = Member::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        $importableRows = collect($rows)
            ->reject(fn ($row) => $this->isEmptyRow($row))
            ->map(fn ($row) => $this->normalizeMappedRow($row, $mapping, 'members'))
            ->filter(fn ($data) => $this->rowValidationError($data, 'members') === null)
            ->reject(fn ($data) => $duplicateStrategy === 'skip' && $this->isDuplicate($data, 'members', $tenantId))
            ->count();

        if ($currentMembers + $importableRows <= (int) $limit) {
            return null;
        }

        return "Der Import würde das Mitgliederlimit überschreiten ({$currentMembers} vorhanden + {$importableRows} neue / {$limit} erlaubt). Bitte reduziere die CSV oder passe die Lizenz an.";
    }

    private function suggestMapping(array $headers, string $type): array
    {
        $aliases = $this->fieldAliases($type);

        return collect($headers)
            ->map(function ($header) use ($aliases) {
                $normalized = Str::of($header)->lower()->ascii()->replace([' ', '-', '_', '.', '/'], '')->toString();

                return $aliases[$normalized] ?? 'skip';
            })
            ->all();
    }

    private function fieldAliases(string $type): array
    {
        $common = [
            'anrede' => 'salutation',
            'titel' => 'title',
            'vorname' => 'first_name',
            'nachname' => 'last_name',
            'name' => 'last_name',
            'firma' => 'company',
            'organisation' => 'company',
            'organization' => 'company',
            'geburtstag' => 'birthday',
            'geburtsdatum' => 'birthday',
            'email' => 'email',
            'emailadresse' => 'email',
            'emailaddress' => 'email',
            'mobil' => 'mobile',
            'mobilfunknummer' => 'mobile',
            'handy' => 'mobile',
            'telefon' => $type === 'contacts' ? 'phone' : 'phone',
            'festnetz' => $type === 'contacts' ? 'phone' : 'phone',
            'strasse' => 'street',
            'straße' => 'street',
            'anschrift' => 'street',
            'plz' => $type === 'contacts' ? 'zip' : 'zip',
            'postleitzahl' => $type === 'contacts' ? 'zip' : 'zip',
            'ort' => 'city',
            'stadt' => 'city',
            'land' => 'country',
            'country' => 'country',
            'notizen' => 'notes',
            'tags' => 'tags',
            'tag' => 'tags',
        ];

        if ($type === 'contacts') {
            return $common + [
                'typ' => 'contact_type',
                'kontaktart' => 'contact_type',
                'art' => 'contact_type',
                'kategorie' => 'category',
                'kontaktgruppe' => 'category',
                'gruppe' => 'category',
                'branche' => 'category',
                'abteilung' => 'department',
                'position' => 'position',
                'funktion' => 'position',
                'ansprechpartner' => 'last_name',
                'webseite' => 'website',
                'website' => 'website',
                'quelle' => 'source',
                'beziehung' => 'relationship',
                'fax' => 'fax',
            ];
        }

        return $common + [
            'mitgliedsnummer' => 'member_id',
            'mitgliednummer' => 'member_id',
            'mitgliedsnr' => 'member_id',
            'mitgliedschaft' => 'membership_name',
            'beitragsart' => 'membership_name',
            'tarif' => 'membership_name',
            'beitragsmodell' => 'membership_name',
            'abteilung' => 'tags',
            'abteilungen' => 'tags',
            'gruppe' => 'tags',
            'gruppen' => 'tags',
            'sparte' => 'tags',
            'sparten' => 'tags',
            'mannschaft' => 'tags',
            'team' => 'tags',
            'eintritt' => 'entry_date',
            'eintrittsdatum' => 'entry_date',
            'austritt' => 'exit_date',
            'austrittsdatum' => 'exit_date',
            'kuendigungsdatum' => 'termination_date',
            'kundigungsdatum' => 'termination_date',
            'iban' => 'iban',
            'bic' => 'bic',
            'sepa' => 'sepa_mandate_reference',
            'mandatsreferenz' => 'sepa_mandate_reference',
            'mitgliedsbeitrag' => 'membership_amount',
            'beitrag' => 'membership_amount',
            'betrag' => 'membership_amount',
            'zahlungsweise' => 'membership_interval',
            'intervall' => 'membership_interval',
            'abbuchungsrhythmus' => 'membership_interval',
            'zahlungsmethode' => 'payment_method',
            'zahlart' => 'payment_method',
            'sepaunterschriebenam' => 'sepa_signed_at',
            'mandatsdatum' => 'sepa_signed_at',
            'kontoinhaber' => 'sepa_account_holder',
            'pflichtstunden' => 'required_service_hours',
            'arbeitsstunden' => 'required_service_hours',
        ];
    }

    private function fieldOptions(string $type): array
    {
        if ($type === 'contacts') {
            return [
                'Kontakt' => [
                    'contact_type' => 'Kontaktart',
                    'category' => 'Kategorie',
                    'organization' => 'Organisation',
                    'department' => 'Abteilung',
                    'position' => 'Position',
                    'first_name' => 'Vorname',
                    'last_name' => 'Nachname',
                    'salutation' => 'Anrede',
                    'title' => 'Titel',
                    'birthday' => 'Geburtstag',
                ],
                'Kommunikation' => [
                    'email' => 'E-Mail',
                    'secondary_email' => 'Weitere E-Mail',
                    'mobile' => 'Mobil',
                    'phone' => 'Telefon',
                    'fax' => 'Fax',
                    'website' => 'Webseite',
                ],
                'Adresse & Beziehung' => [
                    'street' => 'Straße',
                    'address_addition' => 'Adresszusatz',
                    'zip' => 'PLZ',
                    'city' => 'Ort',
                    'country' => 'Land',
                    'source' => 'Quelle',
                    'relationship' => 'Beziehung',
                    'notes' => 'Notizen',
                    'tags' => 'Tags',
                ],
            ];
        }

        return [
            'Mitglied' => [
                'gender' => 'Geschlecht',
                'salutation' => 'Anrede',
                'title' => 'Titel',
                'first_name' => 'Vorname',
                'last_name' => 'Nachname',
                'company' => 'Firma / Organisation',
                'birthday' => 'Geburtstag',
            ],
            'Mitgliedschaft' => [
                'member_id' => 'Mitgliedsnummer',
                'membership_name' => 'Mitgliedschaft / Beitragsart',
                'entry_date' => 'Eintritt',
                'exit_date' => 'Austritt',
                'termination_date' => 'Kündigungsdatum',
                'membership_amount' => 'Beitrag',
                'membership_interval' => 'Zahlungsweise',
                'required_service_hours' => 'Pflichtstunden',
                'tags' => 'Tags / Abteilungen / Gruppen',
            ],
            'Kommunikation' => [
                'email' => 'E-Mail',
                'mobile' => 'Mobilfunknummer',
                'phone' => 'Festnetznummer',
            ],
            'Adresse & Zahlung' => [
                'street' => 'Straße + Nr.',
                'address_extra' => 'Adresszusatz',
                'zip' => 'PLZ',
                'city' => 'Ort',
                'country' => 'Land',
                'co' => 'C/O',
                'iban' => 'IBAN',
                'bic' => 'BIC',
                'sepa_mandate_reference' => 'SEPA-Mandatsreferenz',
                'sepa_signed_at' => 'SEPA unterschrieben am',
                'sepa_account_holder' => 'Kontoinhaber',
                'payment_method' => 'Zahlungsmethode',
            ],
        ];
    }

    private function importConfig(string $type): array
    {
        if ($type === 'contacts') {
            return [
                'type' => 'contacts',
                'label' => 'Kontakte',
                'singular' => 'Kontakt',
                'title' => 'Kontakte importieren',
                'description' => 'Sponsoren, Lieferanten, Presse, Behörden, Trainer oder Eltern sauber übernehmen.',
                'upload_route' => 'import.kontakte.preview',
                'confirm_route' => 'import.kontakte.confirm',
                'index_route' => 'import.kontakte',
                'sources' => $this->sourceProfiles(),
            ];
        }

        return [
            'type' => 'members',
            'label' => 'Mitglieder',
            'singular' => 'Mitglied',
            'title' => 'Mitglieder importieren',
            'description' => 'Bestehende Mitglieder aus Excel, WISO, Campai oder anderen CSV-Quellen übernehmen.',
            'upload_route' => 'import.mitglieder.preview',
            'confirm_route' => 'import.mitglieder.confirm',
            'index_route' => 'import.mitglieder',
            'sources' => $this->sourceProfiles(),
        ];
    }

    private function recentImportRuns(?string $type = null)
    {
        return ImportRun::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->when($type, fn ($query) => $query->where('import_type', $type))
            ->with('creator')
            ->latest()
            ->take(8)
            ->get();
    }

    private function redirectToImport(string $type)
    {
        return redirect()->route($type === 'contacts' ? 'import.kontakte' : 'import.mitglieder');
    }

    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function sourceProfiles(): array
    {
        return [
            'excel' => 'Excel / freie CSV',
            'wiso' => 'WISO MeinVerein',
            'campai' => 'Campai',
            'vereinonline' => 'VereinOnline',
            'easyverein' => 'easyVerein',
            'other' => 'Anderes System',
        ];
    }

    private function normalizeSourceProfile(?string $sourceProfile): string
    {
        return array_key_exists((string) $sourceProfile, $this->sourceProfiles()) ? (string) $sourceProfile : 'excel';
    }

    private function sourceProfileLabel(string $sourceProfile): string
    {
        return $this->sourceProfiles()[$sourceProfile] ?? $this->sourceProfiles()['excel'];
    }

    private function buildReadiness(array $headers, array $rows, array $mapping, string $type): array
    {
        $mappedFields = collect($mapping)->reject(fn ($field) => $field === 'skip')->values();
        $required = $type === 'contacts' ? ['organization|first_name|last_name'] : ['first_name', 'last_name'];
        $recommended = $type === 'contacts'
            ? ['email', 'category', 'city']
            : ['email', 'member_id', 'entry_date', 'membership_amount', 'membership_interval', 'iban'];
        $missingRequired = [];

        foreach ($required as $field) {
            if (str_contains($field, '|')) {
                $alternatives = explode('|', $field);

                if ($mappedFields->intersect($alternatives)->isEmpty()) {
                    $missingRequired[] = 'Name oder Organisation';
                }

                continue;
            }

            if (! $mappedFields->contains($field)) {
                $missingRequired[] = $this->fieldLabel($field, $type);
            }
        }

        $missingRecommended = collect($recommended)
            ->reject(fn ($field) => $mappedFields->contains($field))
            ->map(fn ($field) => $this->fieldLabel($field, $type))
            ->values()
            ->all();

        $analysis = $this->analyzeRows($rows, $mapping, $type);
        $score = 100;
        $score -= count($missingRequired) * 35;
        $score -= min(count($missingRecommended) * 6, 30);
        $score -= min($analysis['warning_count'] * 4, 30);
        $score = max($score, 0);

        return [
            'score' => $score,
            'state' => $score >= 80 ? 'ready' : ($score >= 55 ? 'check' : 'blocked'),
            'mapped_count' => $mappedFields->count(),
            'header_count' => count($headers),
            'row_count' => count($rows),
            'missing_required' => $missingRequired,
            'missing_recommended' => $missingRecommended,
            'warning_count' => $analysis['warning_count'],
        ];
    }

    private function mappingBlockerMessage(array $mapping, string $type): ?string
    {
        $mappedFields = collect($mapping)->reject(fn ($field) => $field === 'skip')->values();

        if ($mappedFields->isEmpty()) {
            return 'Bitte ordne vor dem Import mindestens eine Spalte zu.';
        }

        if ($type === 'contacts') {
            if ($mappedFields->intersect(['organization', 'first_name', 'last_name'])->isEmpty()) {
                return 'Bitte ordne für Kontakte mindestens Organisation, Vorname oder Nachname zu.';
            }

            return null;
        }

        $missing = collect(['first_name' => 'Vorname', 'last_name' => 'Nachname'])
            ->reject(fn ($label, $field) => $mappedFields->contains($field))
            ->values();

        if ($missing->isNotEmpty()) {
            return 'Bitte ordne für Mitglieder mindestens ' . $missing->implode(' und ') . ' zu.';
        }

        return null;
    }

    private function analyzeDuplicates(array $rows, array $mapping, string $type, int $tenantId): array
    {
        $duplicates = [];

        foreach ($rows as $position => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $data = $this->normalizeMappedRow($row, $mapping, $type);

            if ($this->rowValidationError($data, $type) !== null) {
                continue;
            }

            $duplicate = $this->duplicateMatch($data, $type, $tenantId);

            if ($duplicate) {
                $duplicates[] = [
                    'row' => $position + 2,
                    'incoming' => $this->incomingDuplicateLabel($data, $type),
                    'existing' => $duplicate['label'],
                    'reason' => $duplicate['reason'],
                ];
            }
        }

        return [
            'duplicate_count' => count($duplicates),
            'duplicates' => array_slice($duplicates, 0, 10),
        ];
    }

    private function duplicateMatch(array $data, string $type, int $tenantId): ?array
    {
        if ($type === 'contacts') {
            if (filled($data['email'] ?? null)) {
                $contact = Contact::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('email', $data['email'])->first();

                if ($contact) {
                    return [
                        'label' => $contact->organization ?: trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
                        'reason' => 'gleiche E-Mail',
                    ];
                }
            }

            if (filled($data['organization'] ?? null) && blank($data['email'] ?? null)) {
                $contact = Contact::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('organization', $data['organization'])
                    ->where('city', $data['city'] ?? null)
                    ->first();

                if ($contact) {
                    return [
                        'label' => $contact->organization,
                        'reason' => 'gleiche Organisation und gleicher Ort',
                    ];
                }
            }

            return null;
        }

        if (filled($data['member_id'] ?? null)) {
            $member = Member::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('member_id', $data['member_id'])->first();

            if ($member) {
                return [
                    'label' => $member->full_name,
                    'reason' => 'gleiche Mitgliedsnummer',
                ];
            }
        }

        if (filled($data['email'] ?? null)) {
            $member = Member::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('email', $data['email'])->first();

            if ($member) {
                return [
                    'label' => $member->full_name,
                    'reason' => 'gleiche E-Mail',
                ];
            }
        }

        if (filled($data['first_name'] ?? null) && filled($data['last_name'] ?? null)) {
            $member = Member::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->when(filled($data['birthday'] ?? null), fn ($query) => $query->where('birthday', $data['birthday']))
                ->first();

            if ($member) {
                return [
                    'label' => $member->full_name,
                    'reason' => filled($data['birthday'] ?? null) ? 'gleicher Name und Geburtstag' : 'gleicher Name',
                ];
            }
        }

        return null;
    }

    private function incomingDuplicateLabel(array $data, string $type): string
    {
        if ($type === 'contacts') {
            return $data['organization']
                ?? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''))
                ?: 'Kontakt ohne Anzeigename';
        }

        return trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: 'Mitglied ohne Anzeigename';
    }

    private function duplicateStrategyLabel(string $strategy): string
    {
        return $strategy === 'create_new' ? 'Dubletten trotzdem neu anlegen' : 'Dubletten überspringen';
    }

    private function membershipStrategyLabel(string $strategy): string
    {
        return $strategy === 'create_and_assign'
            ? 'Mitgliedschaften aus Import bilden und zuordnen'
            : 'Beitragswerte nur am Mitglied speichern';
    }

    private function customFieldStrategyLabel(string $strategy): string
    {
        return $strategy === 'create_from_unmapped'
            ? 'Ignorierte Spalten als eigene Felder sichern'
            : 'Ignorierte Spalten nicht übernehmen';
    }

    private function skippedRowPayload(int $position, string $reason, array $data, string $type): array
    {
        return [
            'row' => $position + 2,
            'reason' => $reason,
            'incoming' => $this->incomingDuplicateLabel($data, $type),
            'values' => collect($data)
                ->reject(fn ($value, $key) => in_array($key, ['tenant_id', 'import_run_id'], true))
                ->mapWithKeys(fn ($value, $key) => [$this->fieldLabel((string) $key, $type) => is_array($value) ? implode(', ', $value) : $value])
                ->all(),
        ];
    }

    private function mappedFieldLabels(array $mapping, string $type): array
    {
        return collect($mapping)
            ->reject(fn ($field) => $field === 'skip')
            ->map(fn ($field) => $this->fieldLabel((string) $field, $type))
            ->unique()
            ->values()
            ->all();
    }

    private function fieldLabel(string $field, string $type): string
    {
        foreach ($this->fieldOptions($type) as $fields) {
            if (isset($fields[$field])) {
                return $fields[$field];
            }
        }

        return Str::of($field)->replace('_', ' ')->title()->toString();
    }

    private function nextStepsFor(ImportRun $importRun): array
    {
        if ($importRun->import_type === 'contacts') {
            return [
                'Kontakte stichprobenartig öffnen und Kategorien prüfen.',
                'Für Sponsoren und Behörden fehlende Ansprechpartner ergänzen.',
                'Beim nächsten Versand Zielgruppen über Kontakte testen.',
            ];
        }

        return [
            'Mitglieder stichprobenartig öffnen und Beitrag, Zahlungsweise und SEPA prüfen.',
            'Mitgliedschaften oder Beitragsmodelle bei Bedarf nachziehen.',
            'Vor dem ersten Rechnungslauf eine kleine Testgruppe kontrollieren.',
        ];
    }

    private function qualityChecksFor(ImportRun $importRun): array
    {
        if ($importRun->import_type === 'contacts') {
            return $this->contactQualityChecks($importRun);
        }

        return $this->memberQualityChecks($importRun);
    }

    private function qualitySummaryFor(ImportRun $importRun): array
    {
        if ($importRun->status === 'undone') {
            return [
                'required_open' => 0,
                'optional_open' => 0,
                'checks' => [],
                'state' => 'undone',
            ];
        }

        $checks = collect($this->qualityChecksFor($importRun));
        $openChecks = $checks
            ->filter(fn ($check) => ($check['count'] ?? 0) > 0)
            ->sortBy(fn ($check) => ($check['weight'] ?? 'required') === 'required' ? 0 : 1)
            ->values();

        return [
            'required_open' => $openChecks
                ->filter(fn ($check) => ($check['weight'] ?? 'required') === 'required')
                ->sum('count'),
            'optional_open' => $openChecks
                ->filter(fn ($check) => ($check['weight'] ?? 'required') !== 'required')
                ->sum('count'),
            'checks' => $openChecks->take(3)->all(),
            'state' => $openChecks->isEmpty() ? 'ready' : 'needs_work',
        ];
    }

    private function memberQualityChecks(ImportRun $importRun): array
    {
        $members = $importRun->members();
        $withoutEmail = (clone $members)->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''));
        $withoutEntryDate = (clone $members)->whereNull('entry_date');
        $withoutAmount = (clone $members)->whereNull('membership_amount');
        $withoutPaymentMethod = (clone $members)->where(fn ($query) => $query->whereNull('payment_method')->orWhere('payment_method', ''));
        $sepaWithoutIban = (clone $members)
            ->where('payment_method', 'sepa_lastschrift')
            ->where(fn ($query) => $query->whereNull('iban')->orWhere('iban', ''));
        $withoutTags = (clone $members)->doesntHave('tags');

        return [
            $this->qualityCheck(
                $importRun,
                'members_without_email',
                'communication',
                'E-Mail-Adressen',
                'Für Einladungen, Newsletter und Rückfragen.',
                (clone $withoutEmail)->count(),
                'Mitglieder ohne E-Mail prüfen.',
                'required',
                $this->memberCorrectionSamples($withoutEmail)
            ),
            $this->qualityCheck(
                $importRun,
                'members_without_entry_date',
                'membership',
                'Eintrittsdaten',
                'Wichtig für Status, Jubiläen und Auswertungen.',
                (clone $withoutEntryDate)->count(),
                'Eintrittsdaten nachtragen.',
                'required',
                $this->memberCorrectionSamples($withoutEntryDate)
            ),
            $this->qualityCheck(
                $importRun,
                'members_without_amount',
                'billing',
                'Beitragsdaten',
                'Grundlage für Beitragsrechnungen.',
                (clone $withoutAmount)->count(),
                'Mitglieder ohne Beitrag kontrollieren.',
                'required',
                $this->memberCorrectionSamples($withoutAmount)
            ),
            $this->qualityCheck(
                $importRun,
                'members_without_payment_method',
                'billing',
                'Zahlungsart',
                'Wichtig für Rechnung, Barzahlung oder SEPA.',
                (clone $withoutPaymentMethod)->count(),
                'Zahlungsart ergänzen.',
                'required',
                $this->memberCorrectionSamples($withoutPaymentMethod)
            ),
            $this->qualityCheck(
                $importRun,
                'members_sepa_without_iban',
                'sepa',
                'SEPA-Mandate',
                'SEPA-Lastschriften brauchen eine IBAN.',
                (clone $sepaWithoutIban)->count(),
                'IBAN bei SEPA-Mitgliedern prüfen.',
                'required',
                $this->memberCorrectionSamples($sepaWithoutIban)
            ),
            $this->qualityCheck(
                $importRun,
                'members_without_tags',
                'structure',
                'Gruppen und Tags',
                'Hilft bei Zielgruppen, Abteilungen und Filtern.',
                (clone $withoutTags)->count(),
                'Mitglieder ohne Gruppe oder Tag prüfen.',
                'optional',
                $this->memberCorrectionSamples($withoutTags)
            ),
        ];
    }

    private function contactQualityChecks(ImportRun $importRun): array
    {
        $contacts = $importRun->contacts();
        $withoutCategory = (clone $contacts)->where(fn ($query) => $query->whereNull('category')->orWhere('category', ''));
        $withoutEmail = (clone $contacts)->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''));
        $organizationsWithoutPerson = (clone $contacts)
            ->where('contact_type', 'organization')
            ->where(fn ($query) => $query
                ->where(fn ($inner) => $inner->whereNull('first_name')->orWhere('first_name', ''))
                ->where(fn ($inner) => $inner->whereNull('last_name')->orWhere('last_name', '')));
        $withoutCity = (clone $contacts)->where(fn ($query) => $query->whereNull('city')->orWhere('city', ''));

        return [
            $this->qualityCheck(
                $importRun,
                'contacts_without_category',
                'structure',
                'Kategorien',
                'Sponsoren, Lieferanten, Presse und Behörden sauber filtern.',
                (clone $withoutCategory)->count(),
                'Kontakte ohne Kategorie prüfen.',
                'required',
                $this->contactCorrectionSamples($withoutCategory)
            ),
            $this->qualityCheck(
                $importRun,
                'contacts_without_email',
                'communication',
                'E-Mail-Adressen',
                'Für Serienmails und schnelle Rückfragen.',
                (clone $withoutEmail)->count(),
                'Kontakte ohne E-Mail ergänzen.',
                'optional',
                $this->contactCorrectionSamples($withoutEmail)
            ),
            $this->qualityCheck(
                $importRun,
                'contacts_organizations_without_person',
                'relationship',
                'Organisationen mit Ansprechpartner',
                'Bei Firmen, Behörden und Sponsoren ist eine Person oft entscheidend.',
                (clone $organizationsWithoutPerson)->count(),
                'Ansprechpartner bei Organisationen ergänzen.',
                'optional',
                $this->contactCorrectionSamples($organizationsWithoutPerson)
            ),
            $this->qualityCheck(
                $importRun,
                'contacts_without_city',
                'address',
                'Orte',
                'Hilft bei Suche, regionaler Zuordnung und Briefen.',
                (clone $withoutCity)->count(),
                'Ort nachtragen.',
                'optional',
                $this->contactCorrectionSamples($withoutCity)
            ),
        ];
    }

    private function qualityCheck(ImportRun $importRun, string $key, string $area, string $label, string $description, int $count, string $action, string $weight = 'required', array $samples = []): array
    {
        return [
            'key' => $key,
            'area' => $area,
            'label' => $label,
            'description' => $description,
            'count' => $count,
            'action' => $action,
            'weight' => $weight,
            'state' => $count === 0 ? 'ready' : ($weight === 'required' ? 'needs_work' : 'notice'),
            'samples' => $samples,
            'url' => route('import.quality-issue', [$importRun, $key]),
        ];
    }

    private function memberCorrectionSamples($query): array
    {
        return (clone $query)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->take(5)
            ->get()
            ->map(fn (Member $member) => [
                'label' => $member->full_name ?: 'Mitglied #' . $member->id,
                'meta' => collect([$member->member_id, $member->email, $member->city])->filter()->implode(' · '),
                'url' => route('members.edit', $member),
            ])
            ->all();
    }

    private function contactCorrectionSamples($query): array
    {
        return (clone $query)
            ->orderByRaw("
                COALESCE(
                    NULLIF(organization, ''),
                    NULLIF(company, ''),
                    NULLIF(last_name, ''),
                    NULLIF(first_name, ''),
                    email
                ) asc
            ")
            ->take(5)
            ->get()
            ->map(fn (Contact $contact) => [
                'label' => $contact->display_name,
                'meta' => collect([$contact->email, $contact->city])->filter()->implode(' · '),
                'url' => route('contacts.edit', $contact),
            ])
            ->all();
    }

    private function qualityIssueQuery(ImportRun $importRun, string $issue, string $search = '')
    {
        $query = $importRun->import_type === 'contacts'
            ? $importRun->contacts()
            : $importRun->members();

        $query = match ($issue) {
            'members_without_email' => $this->memberQualityBase($importRun)
                ->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''))
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'members_without_entry_date' => $this->memberQualityBase($importRun)
                ->whereNull('entry_date')
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'members_without_amount' => $this->memberQualityBase($importRun)
                ->whereNull('membership_amount')
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'members_without_payment_method' => $this->memberQualityBase($importRun)
                ->where(fn ($query) => $query->whereNull('payment_method')->orWhere('payment_method', ''))
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'members_sepa_without_iban' => $this->memberQualityBase($importRun)
                ->where('payment_method', 'sepa_lastschrift')
                ->where(fn ($query) => $query->whereNull('iban')->orWhere('iban', ''))
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'members_without_tags' => $this->memberQualityBase($importRun)
                ->doesntHave('tags')
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'contacts_without_category' => $this->contactQualityBase($importRun)
                ->where(fn ($query) => $query->whereNull('category')->orWhere('category', ''))
                ->orderByRaw($this->contactQualityOrderSql()),
            'contacts_without_email' => $this->contactQualityBase($importRun)
                ->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''))
                ->orderByRaw($this->contactQualityOrderSql()),
            'contacts_organizations_without_person' => $this->contactQualityBase($importRun)
                ->where('contact_type', 'organization')
                ->where(fn ($query) => $query
                    ->where(fn ($inner) => $inner->whereNull('first_name')->orWhere('first_name', ''))
                    ->where(fn ($inner) => $inner->whereNull('last_name')->orWhere('last_name', '')))
                ->orderByRaw($this->contactQualityOrderSql()),
            'contacts_without_city' => $this->contactQualityBase($importRun)
                ->where(fn ($query) => $query->whereNull('city')->orWhere('city', ''))
                ->orderByRaw($this->contactQualityOrderSql()),
            default => $query->whereRaw('1 = 0'),
        };

        return $this->applyQualityIssueSearch($query, $importRun->import_type, $search);
    }

    private function applyQualityIssueSearch($query, string $type, string $search)
    {
        if ($search === '') {
            return $query;
        }

        return $query->where(function ($inner) use ($type, $search) {
            if ($type === 'contacts') {
                $inner->where('organization', 'like', '%' . $search . '%')
                    ->orWhere('company', 'like', '%' . $search . '%')
                    ->orWhere('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('city', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%');

                return;
            }

            $inner->where('first_name', 'like', '%' . $search . '%')
                ->orWhere('last_name', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('member_id', 'like', '%' . $search . '%')
                ->orWhere('city', 'like', '%' . $search . '%');
        });
    }

    private function memberQualityBase(ImportRun $importRun)
    {
        return $importRun->members()->with(['membership', 'tags']);
    }

    private function contactQualityBase(ImportRun $importRun)
    {
        return $importRun->contacts();
    }

    private function contactQualityOrderSql(): string
    {
        return "
            COALESCE(
                NULLIF(organization, ''),
                NULLIF(company, ''),
                NULLIF(last_name, ''),
                NULLIF(first_name, ''),
                email
            ) asc
        ";
    }

    private function buildQualityIssueSpreadsheet(ImportRun $importRun, string $issue, array $check, string $search = ''): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Korrekturliste');
        $records = $this->qualityIssueQuery($importRun, $issue, $search)->get();

        $sheet->fromArray([
            ['Clubano Korrekturliste'],
            ['Import-ID', $importRun->id],
            ['Bereich', $importRun->type_label],
            ['Prüfung', $check['label']],
            ['Aktion', $check['action']],
            ['Suche', $search ?: 'Keine'],
            ['Offen', $records->count()],
            ['Erstellt am', now()->format('d.m.Y H:i')],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(60);

        $tableStartRow = 10;
        $headers = $importRun->import_type === 'members'
            ? ['Name', 'Mitgliedsnummer', 'E-Mail', 'Ort', 'Mitgliedschaft', 'Beitrag', 'Zahlungsart', 'IBAN']
            : ['Name', 'Typ', 'Kategorie', 'E-Mail', 'Telefon', 'Ort', 'Organisation', 'Ansprechpartner'];

        $sheet->fromArray([$headers], null, 'A' . $tableStartRow);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A' . $tableStartRow . ':' . $lastColumn . $tableStartRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
        ]);

        foreach ($records as $index => $record) {
            $row = $importRun->import_type === 'members'
                ? [
                    $record->full_name,
                    $record->member_id,
                    $record->email,
                    $record->city,
                    $record->membership?->name,
                    $record->membership_amount ? number_format((float) $record->membership_amount, 2, ',', '.') . ' EUR' : '',
                    $record->paymentMethodLabel(),
                    $record->iban,
                ]
                : [
                    $record->display_name,
                    $record->contact_type === 'organization' ? 'Organisation' : 'Person',
                    $record->category,
                    $record->email,
                    $record->phone ?: $record->mobile,
                    $record->city,
                    $record->organization ?: $record->company,
                    trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')),
                ];

            $sheet->fromArray([$row], null, 'A' . ($tableStartRow + $index + 1));
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $sheet->setAutoFilter('A' . $tableStartRow . ':' . $lastColumn . max($tableStartRow, $tableStartRow + $records->count()));
        $sheet->freezePane('A' . ($tableStartRow + 1));

        return $spreadsheet;
    }

    private function buildCorrectionsWorkbook(ImportRun $importRun): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Übersicht');
        $openChecks = collect($this->qualityChecksFor($importRun))
            ->filter(fn ($check) => ($check['count'] ?? 0) > 0)
            ->values();

        $summarySheet->fromArray([
            ['Clubano Korrekturmappe'],
            ['Import-ID', $importRun->id],
            ['Bereich', $importRun->type_label],
            ['Datei', $importRun->filename],
            ['Offene Prüfpunkte', $openChecks->count()],
            ['Erstellt am', now()->format('d.m.Y H:i')],
        ], null, 'A1');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $summarySheet->getColumnDimension('A')->setWidth(28);
        $summarySheet->getColumnDimension('B')->setWidth(58);

        $summaryRow = 8;
        $summarySheet->fromArray([['Prüfung', 'Status', 'Offen', 'Aktion']], null, 'A' . $summaryRow);
        $summarySheet->getStyle('A' . $summaryRow . ':D' . $summaryRow)->applyFromArray($this->spreadsheetHeaderStyle());

        if ($openChecks->isEmpty()) {
            $summarySheet->fromArray([['Keine offenen Nacharbeiten', 'OK', 0, '']], null, 'A' . ($summaryRow + 1));
            $spreadsheet->setActiveSheetIndex(0);

            return $spreadsheet;
        }

        foreach ($openChecks as $index => $check) {
            $summarySheet->fromArray([[
                $check['label'],
                ($check['weight'] ?? 'required') === 'required' ? 'Pflicht' : 'Hinweis',
                $check['count'],
                $check['action'],
            ]], null, 'A' . ($summaryRow + $index + 1));

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->correctionSheetTitle($check['label'], $index + 1));
            $this->fillQualityIssueSheet($sheet, $importRun, $check['key'], $check);
        }

        foreach (['A' => 34, 'B' => 16, 'C' => 10, 'D' => 52] as $column => $width) {
            $summarySheet->getColumnDimension($column)->setWidth($width);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function fillQualityIssueSheet($sheet, ImportRun $importRun, string $issue, array $check, string $search = ''): void
    {
        $records = $this->qualityIssueQuery($importRun, $issue, $search)->get();

        $sheet->fromArray([
            ['Clubano Korrekturliste'],
            ['Import-ID', $importRun->id],
            ['Bereich', $importRun->type_label],
            ['Prüfung', $check['label']],
            ['Aktion', $check['action']],
            ['Suche', $search ?: 'Keine'],
            ['Offen', $records->count()],
            ['Erstellt am', now()->format('d.m.Y H:i')],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(60);

        $tableStartRow = 10;
        $headers = $importRun->import_type === 'members'
            ? ['Name', 'Mitgliedsnummer', 'E-Mail', 'Ort', 'Mitgliedschaft', 'Beitrag', 'Zahlungsart', 'IBAN']
            : ['Name', 'Typ', 'Kategorie', 'E-Mail', 'Telefon', 'Ort', 'Organisation', 'Ansprechpartner'];

        $sheet->fromArray([$headers], null, 'A' . $tableStartRow);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A' . $tableStartRow . ':' . $lastColumn . $tableStartRow)->applyFromArray($this->spreadsheetHeaderStyle());

        foreach ($records as $index => $record) {
            $row = $importRun->import_type === 'members'
                ? $this->qualityIssueMemberRow($record)
                : $this->qualityIssueContactRow($record);

            $sheet->fromArray([$row], null, 'A' . ($tableStartRow + $index + 1));
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $sheet->setAutoFilter('A' . $tableStartRow . ':' . $lastColumn . max($tableStartRow, $tableStartRow + $records->count()));
        $sheet->freezePane('A' . ($tableStartRow + 1));
    }

    private function correctionSheetTitle(string $label, int $position): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $label) ?: 'Liste';

        return Str::limit($position . ' ' . $title, 31, '');
    }

    private function qualityIssueMemberRow(Member $record): array
    {
        return [
            $record->full_name,
            $record->member_id,
            $record->email,
            $record->city,
            $record->membership?->name,
            $record->membership_amount ? number_format((float) $record->membership_amount, 2, ',', '.') . ' EUR' : '',
            $record->paymentMethodLabel(),
            $record->iban,
        ];
    }

    private function qualityIssueContactRow(Contact $record): array
    {
        return [
            $record->display_name,
            $record->contact_type === 'organization' ? 'Organisation' : 'Person',
            $record->category,
            $record->email,
            $record->phone ?: $record->mobile,
            $record->city,
            $record->organization ?: $record->company,
            trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')),
        ];
    }

    private function spreadsheetHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
        ];
    }

    private function releaseChecksFor(ImportRun $importRun): array
    {
        $checks = $this->qualityChecksFor($importRun);

        if ($importRun->import_type === 'contacts') {
            return [
                $this->releaseCheck('Kontaktverwaltung', 'Kontakte können gesucht und gefiltert werden.', $this->missingRequiredCount($checks, ['structure']) === 0),
                $this->releaseCheck('Kommunikation', 'Kontaktverteiler sind nutzbar, einzelne E-Mail-Adressen können fehlen.', $this->missingRequiredCount($checks, ['structure']) === 0),
                $this->releaseCheck('Sponsoren & Partner', 'Organisationen sollten später noch Ansprechpartner bekommen.', true, 'notice'),
            ];
        }

        return [
            $this->releaseCheck('Mitgliederverwaltung', 'Grunddaten sind importiert und nutzbar.', $this->missingRequiredCount($checks, ['membership']) === 0),
            $this->releaseCheck('Beitragsrechnung', 'Beiträge und Zahlungsarten müssen vollständig sein.', $this->missingRequiredCount($checks, ['billing']) === 0),
            $this->releaseCheck('SEPA', 'SEPA-Mitglieder brauchen vollständige Mandatsdaten.', $this->missingRequiredCount($checks, ['sepa']) === 0),
            $this->releaseCheck('Kommunikation', 'E-Mail-Adressen sollten für Versandaktionen vollständig sein.', $this->missingRequiredCount($checks, ['communication']) === 0),
        ];
    }

    private function releaseCheck(string $label, string $description, bool $ready, string $stateWhenReady = 'ready'): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'state' => $ready ? $stateWhenReady : 'blocked',
        ];
    }

    private function missingRequiredCount(array $checks, array $areas): int
    {
        return collect($checks)
            ->filter(fn ($check) => in_array($check['area'], $areas, true))
            ->filter(fn ($check) => ($check['weight'] ?? 'required') === 'required')
            ->sum('count');
    }

    private function buildTemplateSpreadsheet(string $type): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($type === 'contacts' ? 'Kontakte' : 'Mitglieder');

        $headers = $this->templateHeaders($type);
        $exampleRows = $this->templateExampleRows($type);

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($exampleRows, null, 'A2');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
        ]);
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Hinweise');
        $notes->fromArray([
            ['Clubano Importvorlage'],
            ['Die erste Zeile bitte nicht löschen. Sie enthält die Spaltenüberschriften, die Clubano automatisch erkennt.'],
            ['Nicht benötigte Spalten können leer bleiben. Für Mitglieder sind Vorname und Nachname Pflicht. Für Kontakte reicht Organisation oder Name.'],
            ['Datumsformat empfohlen: TT.MM.JJJJ, zum Beispiel 01.01.2026.'],
            ['Beträge bitte als Zahl schreiben, zum Beispiel 75,00.'],
        ], null, 'A1');
        $notes->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $notes->getColumnDimension('A')->setWidth(120);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildReportSpreadsheet(ImportRun $importRun): Spreadsheet
    {
        $summary = $importRun->summary ?? [];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Importbericht');
        $sheet->fromArray([
            ['Clubano Importbericht'],
            ['Import-ID', $importRun->id],
            ['Bereich', $importRun->type_label],
            ['Datei', $importRun->filename],
            ['Quelle', $summary['source_profile_label'] ?? 'Excel / freie CSV'],
            ['Ziel', ($summary['import_goal'] ?? '') ?: 'Nicht angegeben'],
            ['Erstellt am', $importRun->created_at?->format('d.m.Y H:i')],
            ['Status', $importRun->status === 'undone' ? 'Rückgängig gemacht' : 'Abgeschlossen'],
            ['Erkannte Zeilen', $importRun->row_count],
            ['Importiert', $importRun->imported_count],
            ['Übersprungen', $importRun->skipped_count],
            ['Dubletten', $summary['duplicate_count'] ?? 0],
            ['Dubletten-Strategie', $summary['duplicate_strategy_label'] ?? 'Dubletten überspringen'],
            ['Mitgliedschaften', $summary['membership_strategy_label'] ?? ''],
            ['Neu angelegte Mitgliedschaften', $summary['created_membership_count'] ?? 0],
            ['Zusatzspalten', $summary['custom_field_strategy_label'] ?? ''],
            ['Neu angelegte eigene Felder', $summary['created_custom_field_count'] ?? 0],
        ], null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(48);

        $fieldsSheet = $spreadsheet->createSheet();
        $fieldsSheet->setTitle('Felder');
        $fieldsSheet->fromArray([['Zugeordnete Felder']], null, 'A1');
        $fieldsSheet->getStyle('A1')->getFont()->setBold(true);

        foreach (($summary['mapped_fields'] ?? []) as $index => $field) {
            $fieldsSheet->setCellValue('A' . ($index + 2), $field);
        }

        $fieldsSheet->getColumnDimension('A')->setWidth(42);

        $skippedSheet = $spreadsheet->createSheet();
        $skippedSheet->setTitle('Korrekturliste');
        $skippedSheet->fromArray([['Zeile', 'Grund', 'Datensatz', 'Werte']], null, 'A1');
        $skippedSheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
        ]);

        foreach (($summary['skipped_rows'] ?? []) as $index => $row) {
            $values = collect($row['values'] ?? [])
                ->map(fn ($value, $label) => $label . ': ' . $value)
                ->implode(' | ');

            $skippedSheet->fromArray([[
                $row['row'] ?? '',
                $row['reason'] ?? '',
                $row['incoming'] ?? '',
                $values,
            ]], null, 'A' . ($index + 2));
        }

        foreach (['A' => 12, 'B' => 34, 'C' => 34, 'D' => 90] as $column => $width) {
        $skippedSheet->getColumnDimension($column)->setWidth($width);
        }

        $qualitySheet = $spreadsheet->createSheet();
        $qualitySheet->setTitle('Qualitätsprüfung');
        $qualitySheet->fromArray([['Bereich', 'Status', 'Offen', 'Prüfung', 'Hinweis', 'Aktion', 'Beispiele']], null, 'A1');
        $qualitySheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A'],
            ],
        ]);

        foreach ($this->qualityChecksFor($importRun) as $index => $check) {
            $qualitySheet->fromArray([[
                $check['area'],
                $check['state'] === 'ready' ? 'OK' : ($check['state'] === 'notice' ? 'Hinweis' : 'Prüfen'),
                $check['count'],
                $check['label'],
                $check['description'],
                $check['count'] > 0 ? $check['action'] : '',
                collect($check['samples'] ?? [])->map(fn ($sample) => trim($sample['label'] . (($sample['meta'] ?? '') ? ' (' . $sample['meta'] . ')' : '')))->implode(' | '),
            ]], null, 'A' . ($index + 2));
        }

        foreach (['A' => 18, 'B' => 14, 'C' => 10, 'D' => 32, 'E' => 58, 'F' => 42, 'G' => 70] as $column => $width) {
            $qualitySheet->getColumnDimension($column)->setWidth($width);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function templateHeaders(string $type): array
    {
        if ($type === 'contacts') {
            return [[
                'Kontaktart',
                'Kategorie',
                'Organisation',
                'Abteilung',
                'Position',
                'Vorname',
                'Nachname',
                'Anrede',
                'E-Mail',
                'Weitere E-Mail',
                'Mobil',
                'Telefon',
                'Straße',
                'Adresszusatz',
                'PLZ',
                'Ort',
                'Land',
                'Webseite',
                'Quelle',
                'Beziehung',
                'Notizen',
                'Tags',
            ]];
        }

        return [[
            'Mitgliedsnummer',
            'Mitgliedschaft',
            'Anrede',
            'Vorname',
            'Nachname',
            'Firma',
            'Geburtsdatum',
            'Eintritt',
            'Austritt',
            'Kündigungsdatum',
            'E-Mail',
            'Mobil',
            'Telefon',
            'Straße',
            'Adresszusatz',
            'PLZ',
            'Ort',
            'Land',
            'Beitrag',
            'Zahlungsweise',
            'Zahlungsmethode',
            'IBAN',
            'BIC',
            'SEPA-Mandatsreferenz',
            'SEPA unterschrieben am',
            'Kontoinhaber',
            'Pflichtstunden',
            'Tags',
        ]];
    }

    private function templateExampleRows(string $type): array
    {
        if ($type === 'contacts') {
            return [
                ['organization', 'Sponsor', 'Demo Druckerei GmbH', 'Marketing', 'Geschäftsführung', 'Clara', 'Beispiel', 'Frau', 'clara@example.test', '', '015112345678', '050661234', 'Musterstraße 12', '', '12345', 'Demostadt', 'Deutschland', 'https://example.test', 'Altbestand', 'Sponsor', 'Ansprechpartnerin für Anzeigen', 'Sponsor;Presse'],
                ['person', 'Behörde', '', 'Sport', 'Sachbearbeitung', 'Max', 'Muster', 'Herr', 'max@example.test', '', '', '050669876', 'Rathausplatz 1', '', '12345', 'Demostadt', 'Deutschland', '', 'Altbestand', 'Stadtverwaltung', '', 'Behörde'],
            ];
        }

        return [
            ['M-0001', 'Aktiv', 'Frau', 'Mia', 'Muster', '', '14.03.1992', '01.01.2026', '', '', 'mia@example.test', '015112345678', '050661234', 'Musterstraße 12', '', '12345', 'Demostadt', 'Deutschland', '75,00', 'vierteljährlich', 'SEPA', 'DE02120300000000202051', 'BYLADEM1001', 'M-0001-2026', '01.01.2026', 'Mia Muster', '10', 'Vorstand;Aktiv'],
            ['M-0002', 'Fördermitglied', 'Herr', 'Tom', 'Test', 'Testfirma GmbH', '20.08.1988', '01.02.2026', '', '', 'tom@example.test', '', '050665555', 'Beispielweg 4', 'c/o Vorstand', '12345', 'Demostadt', 'Deutschland', '120,00', 'jährlich', 'Überweisung', '', '', '', '', '', '0', 'Fördermitglied'],
        ];
    }
}
