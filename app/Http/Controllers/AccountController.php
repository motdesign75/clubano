<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'balance');

        if (! in_array($tab, ['balance', 'erloes', 'inaktiv'], true)) {
            $tab = 'balance';
        }

        $balanceAccounts = Account::forCurrentTenant()
            ->where('active', true)
            ->whereIn('type', ['bank', 'kasse'])
            ->orderBy('number')
            ->get();

        $chartAccounts = Account::forCurrentTenant()
            ->where('active', true)
            ->whereIn('type', ['einnahme', 'ausgabe'])
            ->orderBy('number')
            ->get();

        $inactiveAccounts = Account::forCurrentTenant()
            ->where('active', false)
            ->orderBy('number')
            ->get();

        return view('accounts.index', compact('balanceAccounts', 'chartAccounts', 'inactiveAccounts', 'tab'));
    }

    public function useSimpleChart()
    {
        $tenantId = auth()->user()->tenant_id;
        $created = 0;
        $updated = 0;

        foreach ($this->simpleChartAccounts() as $data) {
            $account = Account::query()
                ->where('tenant_id', $tenantId)
                ->where('number', $data['number'])
                ->where('name', $data['name'])
                ->first();

            if ($account) {
                $account->fill($data + [
                    'active' => true,
                    'import_source' => 'Clubano Standardrahmen',
                ])->save();
                $updated++;
            } else {
                Account::create($data + [
                    'tenant_id' => $tenantId,
                    'active' => true,
                    'online' => false,
                    'balance_start' => 0,
                    'balance_current' => 0,
                    'import_source' => 'Clubano Standardrahmen',
                ]);
                $created++;
            }
        }

        return redirect()
            ->route('accounts.index', ['tab' => 'erloes'])
            ->with('success', "{$created} Konten wurden angelegt, {$updated} bestehende Konten aktualisiert.");
    }

    public function importChart(Request $request)
    {
        $validated = $request->validate([
            'chart_name' => ['nullable', 'string', 'max:120'],
            'chart_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $existingAccountCount = Account::query()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($existingAccountCount > 0 && ! $request->boolean('confirm_existing_chart_import')) {
            return back()
                ->withErrors([
                    'confirm_existing_chart_import' => 'Bitte bestätige ausdrücklich, dass bereits Konten vorhanden sind und der neue Kontenrahmen bestehende Konten aktualisieren kann.',
                ])
                ->withInput();
        }

        $path = $request->file('chart_file')->getRealPath();
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return back()->with('error', 'Die Datei konnte nicht gelesen werden.');
        }

        $header = fgetcsv($handle, 0, ';');

        if (! $header) {
            fclose($handle);

            return back()->with('error', 'Die Datei enthält keine lesbare Kopfzeile.');
        }

        $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $required = ['kontonummer', 'bezeichnung'];

        if (count(array_intersect($required, $header)) !== count($required)) {
            fclose($handle);

            return back()->with('error', 'Bitte lade eine CSV mit mindestens Kontonummer und Bezeichnung hoch.');
        }

        $chartName = trim((string) ($validated['chart_name'] ?? '')) ?: 'Importierter Kontenrahmen';
        $created = 0;
        $updated = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $data = $this->mapChartRow($header, $row);
            $number = trim((string) ($data['kontonummer'] ?? ''));
            $name = trim((string) ($data['bezeichnung'] ?? ''));

            if ($number === '' || $name === '') {
                $skipped++;
                continue;
            }

            $accountData = [
                'tenant_id' => $tenantId,
                'number' => $number,
                'name' => $name,
                'type' => $this->inferAccountType($number, $name),
                'tax_area' => 'ideell',
                'chart_name' => $chartName,
                'tax_key' => trim((string) ($data['steuerschluessel'] ?? '')) ?: null,
                'is_postable' => $this->yesNo((string) ($data['buchbar'] ?? 'Ja')),
                'datev_automatic' => $this->yesNo((string) ($data['datevautomatik'] ?? 'Nein')),
                'active' => $this->yesNo((string) ($data['buchbar'] ?? 'Ja')),
                'online' => false,
                'balance_start' => 0,
                'balance_current' => 0,
                'import_source' => $request->file('chart_file')->getClientOriginalName(),
            ];

            $account = Account::query()
                ->where('tenant_id', $tenantId)
                ->where('number', $number)
                ->where('name', $name)
                ->first();

            if ($account) {
                $account->fill($accountData)->save();
                $updated++;
            } else {
                Account::create($accountData);
                $created++;
            }
        }

        fclose($handle);

        return redirect()
            ->route('accounts.index', ['tab' => 'erloes'])
            ->with('success', "{$created} Konten importiert, {$updated} aktualisiert, {$skipped} Zeilen übersprungen.");
    }

    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        // Checkbox-Werte vorbereiten
        $request->merge([
            'active'   => $request->has('active'),
            'online'   => $request->has('online'),
        ]);

        $validated = $request->validate([
            'number'         => ['nullable', 'string', 'max:20'],
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:bank,kasse,einnahme,ausgabe'],
            'iban'           => ['nullable', 'string', 'max:34'],
            'bic'            => ['nullable', 'string', 'max:11'],
            'description'    => ['nullable', 'string'],
            'balance_start'  => ['nullable', 'numeric'],
            'balance_date'   => ['nullable', 'date'],
            'tax_area'       => ['nullable', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'chart_name'     => ['nullable', 'string', 'max:120'],
            'tax_key'        => ['nullable', 'string', 'max:40'],
            'is_postable'    => ['nullable', 'boolean'],
            'datev_automatic'=> ['nullable', 'boolean'],
            'active'         => ['required', 'boolean'],
            'online'         => ['required', 'boolean'],
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['balance_current'] = $validated['balance_start'] ?? 0;

        $account = Account::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konto erfolgreich erstellt.',
                'account' => $account,
            ]);
        }

        return redirect()->route('accounts.index')->with('success', 'Konto erfolgreich erstellt.');
    }

    public function edit(Account $account)
    {
        $this->authorizeAccount($account);

        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, Account $account)
    {
        $this->authorizeAccount($account);

        // Checkbox-Werte vorbereiten
        $request->merge([
            'active'   => $request->has('active'),
            'online'   => $request->has('online'),
        ]);

        $validated = $request->validate([
            'number'         => ['nullable', 'string', 'max:20'],
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:bank,kasse,einnahme,ausgabe'],
            'iban'           => ['nullable', 'string', 'max:34'],
            'bic'            => ['nullable', 'string', 'max:11'],
            'description'    => ['nullable', 'string'],
            'balance_start'  => ['nullable', 'numeric'],
            'balance_date'   => ['nullable', 'date'],
            'tax_area'       => ['nullable', 'in:ideell,zweckbetrieb,vermoegensverwaltung,wirtschaftlich'],
            'chart_name'     => ['nullable', 'string', 'max:120'],
            'tax_key'        => ['nullable', 'string', 'max:40'],
            'is_postable'    => ['nullable', 'boolean'],
            'datev_automatic'=> ['nullable', 'boolean'],
            'active'         => ['required', 'boolean'],
            'online'         => ['required', 'boolean'],
        ]);

        $account->update($validated);
        $account->updateBalance();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konto erfolgreich aktualisiert.',
                'account' => $account,
            ]);
        }

        return redirect()->route('accounts.index')->with('success', 'Konto erfolgreich aktualisiert.');
    }

    public function hide(Account $account)
    {
        $this->authorizeAccount($account);

        $account->forceFill(['active' => false])->save();

        return redirect()
            ->route('accounts.index', ['tab' => 'inaktiv'])
            ->with('success', "Das Konto {$account->number} {$account->name} wurde ausgeblendet.");
    }

    public function restore(Account $account)
    {
        $this->authorizeAccount($account);

        $account->forceFill(['active' => true])->save();

        $tab = in_array($account->type, ['bank', 'kasse'], true) ? 'balance' : 'erloes';

        return redirect()
            ->route('accounts.index', ['tab' => $tab])
            ->with('success', "Das Konto {$account->number} {$account->name} ist wieder sichtbar.");
    }

    public function bulkVisibility(Request $request)
    {
        $validated = $request->validate([
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
            'action' => ['required', 'in:hide,restore'],
        ], [
            'account_ids.required' => 'Bitte wähle mindestens ein Konto aus.',
        ]);

        $active = $validated['action'] === 'restore';
        $count = Account::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('id', $validated['account_ids'])
            ->update(['active' => $active]);

        $message = $active
            ? "{$count} Konten wurden eingeblendet."
            : "{$count} Konten wurden ausgeblendet.";

        return redirect()
            ->route('accounts.index', ['tab' => $active ? 'erloes' : 'inaktiv'])
            ->with('success', $message);
    }

    public function destroy(Account $account)
    {
        $this->authorizeAccount($account);
        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Konto gelöscht.');
    }

    protected function authorizeAccount(Account $account)
    {
        if ((string) $account->tenant_id !== (string) auth()->user()->tenant_id) {
            abort(403, 'Kein Zugriff auf dieses Konto.');
        }
    }

    protected function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->toString();

        return match ($value) {
            'steuerschlussel' => 'steuerschluessel',
            'datevautomatik' => 'datevautomatik',
            default => $value,
        };
    }

    /**
     * @param array<int, string> $header
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    protected function mapChartRow(array $header, array $row): array
    {
        $mapped = [];

        foreach ($header as $index => $key) {
            $mapped[$key] = $row[$index] ?? null;
        }

        return $mapped;
    }

    protected function yesNo(string $value): bool
    {
        return in_array(Str::of($value)->lower()->ascii()->trim()->toString(), ['ja', 'yes', '1', 'true'], true);
    }

    protected function inferAccountType(string $number, string $name): string
    {
        $normalizedName = Str::of($name)->lower()->ascii()->toString();
        $number = trim($number);

        if (str_contains($normalizedName, 'bank')) {
            return 'bank';
        }

        if (str_contains($normalizedName, 'kasse') || str_contains($normalizedName, 'bar')) {
            return 'kasse';
        }

        if (str_starts_with($number, '8')
            || str_contains($normalizedName, 'erlos')
            || str_contains($normalizedName, 'erlös')
            || str_contains($normalizedName, 'beitrag')
            || str_contains($normalizedName, 'spende')
            || str_contains($normalizedName, 'zuwendung')) {
            return 'einnahme';
        }

        return 'ausgabe';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function simpleChartAccounts(): array
    {
        return [
            ['number' => '1000', 'name' => 'Bank', 'type' => 'bank', 'tax_area' => 'ideell', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '1010', 'name' => 'Kasse', 'type' => 'kasse', 'tax_area' => 'ideell', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '4000', 'name' => 'Mitgliedsbeiträge', 'type' => 'einnahme', 'tax_area' => 'ideell', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '4040', 'name' => 'Spenden und Zuwendungen', 'type' => 'einnahme', 'tax_area' => 'ideell', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '4200', 'name' => 'Sponsoring und Anzeigen', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '4300', 'name' => 'Veranstaltungserlöse', 'type' => 'einnahme', 'tax_area' => 'zweckbetrieb', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '6300', 'name' => 'Verwaltungskosten', 'type' => 'ausgabe', 'tax_area' => 'ideell', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '6304', 'name' => 'Veranstaltungskosten', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '6420', 'name' => 'Verbandsbeiträge', 'type' => 'ausgabe', 'tax_area' => 'ideell', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
            ['number' => '6855', 'name' => 'Nebenkosten des Geldverkehrs', 'type' => 'ausgabe', 'tax_area' => 'ideell', 'chart_name' => 'Clubano Standard', 'is_postable' => true, 'datev_automatic' => false],
        ];
    }
}
