<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Member;
use App\Models\ImportRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    /**
     * Zeigt das Upload-Formular für die CSV-Datei.
     */
    public function showUploadForm()
    {
        $recentImportRuns = ImportRun::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->with('creator')
            ->latest()
            ->take(5)
            ->get();

        return view('import.mitglieder.upload', compact('recentImportRuns'));
    }

    /**
     * Zeigt eine Vorschau der CSV-Datei und ermöglicht die Feldzuordnung.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->store('temp');

        $file = Storage::get($path);
        $lines = array_map('str_getcsv', explode("\n", $file));
        $headers = $lines[0] ?? [];
        $rows = array_slice($lines, 1, 5); // Vorschau: max. 5 Zeilen

        return view('import.mitglieder.preview', compact('headers', 'rows', 'path'));
    }

    /**
     * Führt den Import der Mitglieder auf Basis des Mappings durch.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'mapping' => 'required|array',
        ]);

        $file = Storage::get($request->input('path'));
        $lines = array_map('str_getcsv', explode("\n", $file));

        $headers = $lines[0] ?? [];
        $rows = array_slice($lines, 1);

        $allowedGenders = ['männlich', 'weiblich', 'divers'];
        $allowedSalutations = ['Herr', 'Frau', 'Divers', 'Liebe', 'Lieber', 'Hallo'];
        $dateFields = ['birthday', 'entry_date', 'exit_date', 'cancellation_date', 'termination_date'];

        $tenantId = auth()->user()->tenant_id;
        $importRun = null;
        $importedCount = 0;

        DB::transaction(function () use (
            $request,
            $rows,
            $allowedGenders,
            $allowedSalutations,
            $dateFields,
            $tenantId,
            &$importRun,
            &$importedCount
        ) {
            $importRun = ImportRun::create([
                'tenant_id' => $tenantId,
                'created_by' => auth()->id(),
                'filename' => basename((string) $request->input('path')),
                'status' => 'completed',
                'row_count' => count($rows),
                'imported_count' => 0,
            ]);

            foreach ($rows as $row) {
                if (!is_array($row) || count($row) === 0 || empty(trim((string) ($row[0] ?? '')))) {
                    continue;
                }

                $data = [];

                foreach ($request->input('mapping') as $index => $field) {
                    if ($field === 'skip' || !isset($row[$index])) {
                        continue;
                    }

                    $value = trim((string) $row[$index]);

                    if ($value === '') {
                        $value = null;
                    }

                    if ($field === 'gender' && $value !== null) {
                        if (!in_array(Str::lower($value), $allowedGenders)) {
                            $value = null;
                        } else {
                            $value = ucfirst(Str::lower($value));
                        }
                    }

                    if ($field === 'salutation' && $value !== null && !in_array($value, $allowedSalutations)) {
                        $value = null;
                    }

                    if (in_array($field, $dateFields, true) && $value !== null) {
                        try {
                            $value = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
                        } catch (\Exception $e) {
                            $value = null;
                        }
                    }

                    $field = match ($field) {
                        'company' => 'organization',
                        'phone' => 'landline',
                        'address_extra' => 'address_addition',
                        'co' => 'care_of',
                        default => $field,
                    };

                    $data[$field] = $value;
                }

                $data['tenant_id'] = $tenantId;
                $data['import_run_id'] = $importRun->id;

                if (!empty($data['first_name']) || !empty($data['last_name'])) {
                    Member::create($data);
                    $importedCount++;
                }
            }

            $importRun->forceFill([
                'imported_count' => $importedCount,
            ])->save();
        });

        return redirect()
            ->route('import.mitglieder')
            ->with('success', 'Mitglieder erfolgreich importiert. Der Import kann bei Bedarf rueckgaengig gemacht werden.');
    }

    public function undo(ImportRun $importRun)
    {
        abort_unless((int) $importRun->tenant_id === (int) auth()->user()->tenant_id, 403);

        if (! $importRun->isUndoable()) {
            return redirect()
                ->route('import.mitglieder')
                ->with('error', 'Dieser Import kann nicht mehr rueckgaengig gemacht werden.');
        }

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
            return redirect()
                ->route('import.mitglieder')
                ->with('error', 'Der Import kann nicht mehr automatisch rueckgaengig gemacht werden, weil bereits Folgeaktionen an einzelnen Mitgliedern haengen.');
        }

        DB::transaction(function () use ($members, $importRun) {
            foreach ($members as $member) {
                $member->tags()->detach();
                $member->customValues()->delete();
                $member->delete();
            }

            $importRun->forceFill([
                'status' => 'undone',
                'undone_at' => now(),
                'imported_count' => 0,
            ])->save();
        });

        return redirect()
            ->route('import.mitglieder')
            ->with('success', 'Der Import wurde rueckgaengig gemacht.');
    }
}
