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

    private function showTypedUploadForm(string $type)
    {
        $recentImportRuns = $this->recentImportRuns($type);
        $config = $this->importConfig($type);

        return view('import.upload', compact('recentImportRuns', 'config'));
    }

    private function previewTypedImport(Request $request, string $type)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->store('temp');
        $parsed = $this->readCsvFromStorage($path);
        $config = $this->importConfig($type);
        $fieldOptions = $this->fieldOptions($type);
        $suggestedMapping = $this->suggestMapping($parsed['headers'], $type);
        $previewRows = array_slice($parsed['rows'], 0, 8);
        $analysis = $this->analyzeRows($parsed['rows'], $suggestedMapping, $type);

        return view('import.preview', [
            'config' => $config,
            'path' => $path,
            'headers' => $parsed['headers'],
            'rows' => $previewRows,
            'delimiter' => $parsed['delimiter'],
            'rowCount' => count($parsed['rows']),
            'fieldOptions' => $fieldOptions,
            'suggestedMapping' => $suggestedMapping,
            'analysis' => $analysis,
        ]);
    }

    private function confirmTypedImport(Request $request, string $type)
    {
        $request->validate([
            'path' => 'required|string',
            'mapping' => 'required|array',
        ]);

        $parsed = $this->readCsvFromStorage($request->input('path'));
        $rows = $parsed['rows'];
        $mapping = $request->input('mapping');
        $tenantId = auth()->user()->tenant_id;
        $importedCount = 0;
        $skippedRows = [];

        if ($type === 'members' && $limitMessage = $this->memberImportLimitMessage($rows, $mapping, $tenantId)) {
            return redirect()
                ->route('import.mitglieder')
                ->with('error', $limitMessage);
        }

        DB::transaction(function () use ($type, $request, $rows, $mapping, $tenantId, &$importedCount, &$skippedRows) {
            $importRun = ImportRun::create([
                'tenant_id' => $tenantId,
                'import_type' => $type,
                'created_by' => auth()->id(),
                'filename' => basename((string) $request->input('path')),
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
                    $skippedRows[] = [
                        'row' => $position + 2,
                        'reason' => $validationError,
                    ];
                    continue;
                }

                if ($this->isDuplicate($data, $type, $tenantId)) {
                    $skippedRows[] = [
                        'row' => $position + 2,
                        'reason' => 'Mögliche Dublette vorhanden',
                    ];
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

        return $this->redirectToImport($type)->with('success', $message);
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

    private function memberImportLimitMessage(array $rows, array $mapping, int $tenantId): ?string
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
            ->reject(fn ($data) => $this->isDuplicate($data, 'members', $tenantId))
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
}
