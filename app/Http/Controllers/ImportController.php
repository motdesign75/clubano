<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\Member;
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

        return view('import.index', compact('recentImportRuns'));
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
        ]);
    }

    public function template(string $type)
    {
        abort_unless(in_array($type, ['mitglieder', 'kontakte'], true), 404);

        $importType = $type === 'kontakte' ? 'contacts' : 'members';
        $spreadsheet = $this->buildTemplateSpreadsheet($importType);
        $filename = 'clubano-importvorlage-' . $type . '.xlsx';
        $path = storage_path('app/temp/' . Str::uuid() . '-' . $filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function reportExport(ImportRun $importRun)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        $spreadsheet = $this->buildReportSpreadsheet($importRun);
        $filename = 'clubano-importbericht-' . $importRun->id . '.xlsx';
        $path = storage_path('app/temp/' . Str::uuid() . '-' . $filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
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
        ]);

        $parsed = $this->readTabularFileFromStorage($request->input('path'), $request->input('original_filename'));
        $rows = $parsed['rows'];
        $mapping = $request->input('mapping');
        $tenantId = auth()->user()->tenant_id;
        $importedCount = 0;
        $skippedRows = [];
        $importRun = null;
        $sourceProfile = $this->normalizeSourceProfile($request->input('source_profile'));
        $duplicateStrategy = $request->input('duplicate_strategy', 'skip');
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

        DB::transaction(function () use ($type, $request, $rows, $mapping, $tenantId, $parsed, $sourceProfile, $duplicateStrategy, $duplicateAnalysisBeforeImport, &$importRun, &$importedCount, &$skippedRows) {
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
                    unset($data['tags']);
                    Member::create($data);
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

            if ($value !== null && $field !== 'tags') {
                $data[$field] = $value;
            } elseif ($field === 'tags' && $value !== null) {
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

        if (in_array($field, ['is_active', 'is_favorite', 'consent_email', 'consent_phone', 'consent_post', 'gdpr_consent'], true)) {
            return in_array(Str::lower($value), ['1', 'ja', 'yes', 'true', 'x'], true);
        }

        if ($field === 'contact_type') {
            $normalized = Str::lower($value);

            return in_array($normalized, ['person', 'organization'], true) ? $normalized : 'person';
        }

        if ($field === 'tags') {
            return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $value) ?: [])));
        }

        return $value;
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
        $data['contact_type'] = $data['contact_type'] ?? (filled($data['organization'] ?? null) && blank($data['first_name'] ?? null) ? 'organization' : 'person');
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
        ];

        if ($type === 'contacts') {
            return $common + [
                'typ' => 'contact_type',
                'kontaktart' => 'contact_type',
                'kategorie' => 'category',
                'abteilung' => 'department',
                'position' => 'position',
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
                'entry_date' => 'Eintritt',
                'exit_date' => 'Austritt',
                'termination_date' => 'Kündigungsdatum',
                'membership_amount' => 'Beitrag',
                'membership_interval' => 'Zahlungsweise',
                'required_service_hours' => 'Pflichtstunden',
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
            ['M-0001', 'Frau', 'Mia', 'Muster', '', '14.03.1992', '01.01.2026', '', '', 'mia@example.test', '015112345678', '050661234', 'Musterstraße 12', '', '12345', 'Demostadt', 'Deutschland', '75,00', 'vierteljährlich', 'SEPA', 'DE02120300000000202051', 'BYLADEM1001', 'M-0001-2026', '01.01.2026', 'Mia Muster', '10'],
            ['M-0002', 'Herr', 'Tom', 'Test', 'Testfirma GmbH', '20.08.1988', '01.02.2026', '', '', 'tom@example.test', '', '050665555', 'Beispielweg 4', 'c/o Vorstand', '12345', 'Demostadt', 'Deutschland', '120,00', 'jährlich', 'Überweisung', '', '', '', '', '', '0'],
        ];
    }
}
