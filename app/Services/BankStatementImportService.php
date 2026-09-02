<?php

namespace App\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class BankStatementImportService
{
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $content = file_get_contents($file->getRealPath());

        if ($content === false || trim($content) === '') {
            throw new RuntimeException('Die Datei ist leer oder konnte nicht gelesen werden.');
        }

        if (in_array($extension, ['xml', 'camt'], true) || str_contains(Str::lower($content), '<document')) {
            return [
                'format' => 'CAMT.053',
                'rows' => $this->parseCamt($content),
            ];
        }

        if (in_array($extension, ['sta', 'mta', 'mt940'], true) || str_contains($content, ':61:')) {
            return [
                'format' => 'MT940',
                'rows' => $this->parseMt940($content),
            ];
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            return [
                'format' => 'CSV',
                'rows' => $this->parseCsv($file->getRealPath()),
            ];
        }

        throw new RuntimeException('Bitte lade eine CAMT.053-XML-Datei, eine MT940/MTA-Datei oder eine CSV-Datei hoch.');
    }

    private function parseCamt(string $content): array
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($content)) {
            throw new RuntimeException('Die CAMT-Datei ist kein gültiges XML.');
        }

        $xpath = new DOMXPath($dom);
        $entries = $xpath->query('//*[local-name()="Ntry"]');
        $rows = [];

        foreach ($entries ?: [] as $entry) {
            if (! $entry instanceof DOMElement) {
                continue;
            }

            $amount = $this->decimal($this->text($xpath, './*[local-name()="Amt"]', $entry));
            if ($amount <= 0) {
                continue;
            }

            $creditDebit = strtoupper($this->text($xpath, './*[local-name()="CdtDbtInd"]', $entry));
            $signedAmount = $creditDebit === 'DBIT' ? -1 * $amount : $amount;
            $bookingDate = $this->dateFromNode($xpath, './*[local-name()="BookgDt"]', $entry);
            $valueDate = $this->dateFromNode($xpath, './*[local-name()="ValDt"]', $entry);

            $details = $xpath->query('.//*[local-name()="TxDtls"]', $entry)->item(0);
            $counterparty = $details instanceof DOMElement
                ? ($this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="Dbtr"]//*[local-name()="Nm"]', $details)
                    ?: $this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="Cdtr"]//*[local-name()="Nm"]', $details)
                    ?: $this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="UltmtDbtr"]//*[local-name()="Nm"]', $details)
                    ?: $this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="UltmtCdtr"]//*[local-name()="Nm"]', $details))
                : null;
            $counterpartyIban = $details instanceof DOMElement
                ? ($this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="DbtrAcct"]//*[local-name()="IBAN"]', $details)
                    ?: $this->text($xpath, './/*[local-name()="RltdPties"]//*[local-name()="CdtrAcct"]//*[local-name()="IBAN"]', $details))
                : null;
            $purpose = $details instanceof DOMElement
                ? $this->texts($xpath, './/*[local-name()="RmtInf"]//*[local-name()="Ustrd"]', $details)
                : null;
            $endToEndId = $details instanceof DOMElement
                ? $this->text($xpath, './/*[local-name()="Refs"]//*[local-name()="EndToEndId"]', $details)
                : null;

            $bankReference = $this->text($xpath, './*[local-name()="AcctSvcrRef"]', $entry)
                ?: $this->text($xpath, './*[local-name()="NtryRef"]', $entry);

            $rows[] = $this->normalizeRow([
                'booking_date' => $bookingDate,
                'value_date' => $valueDate,
                'amount' => $signedAmount,
                'currency' => $this->currency($xpath, './*[local-name()="Amt"]', $entry),
                'counterparty_name' => $counterparty,
                'counterparty_iban' => $counterpartyIban,
                'purpose' => $purpose ?: $this->text($xpath, './*[local-name()="AddtlNtryInf"]', $entry),
                'end_to_end_id' => $endToEndId,
                'bank_reference' => $bankReference,
                'raw' => [
                    'credit_debit' => $creditDebit,
                    'entry_reference' => $this->text($xpath, './*[local-name()="NtryRef"]', $entry),
                ],
            ]);
        }

        return $rows;
    }

    private function parseCsv(string $path): array
    {
        $delimiter = $this->detectDelimiter($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Die CSV-Datei konnte nicht gelesen werden.');
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);
            throw new RuntimeException('Die CSV-Datei enthält keine Kopfzeile.');
        }

        $normalizedHeader = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $data = [];
            foreach ($normalizedHeader as $index => $key) {
                $data[$key] = $line[$index] ?? null;
            }

            $amount = $this->csvAmount($data);
            if ($amount === null || abs($amount) <= 0) {
                continue;
            }

            $counterpartyName = $this->first($data, [
                'name',
                'auftraggeberempfanger',
                'auftraggeberempfaenger',
                'empfanger',
                'empfaenger',
                'auftraggeber',
                'begunstigter',
                'beguenstigter',
                'zahlungspflichtiger',
                'begunstigterzahlungspflichtiger',
                'beguenstigterzahlungspflichtiger',
                'namezahlungsbeteiligter',
                'zahlungsbeteiligter',
                'nameauftraggeber',
                'nameempfanger',
                'nameempfaenger',
                'kontoinhaber',
                'partnername',
            ]);

            $purpose = $this->combine($data, [
                'verwendungszweck',
                'verwendungszweck1',
                'verwendungszweck2',
                'verwendungszweck3',
                'vwz',
                'vwz1',
                'vwz2',
                'zweck',
                'buchungstext',
                'text',
                'umsatztext',
                'beschreibung',
            ]);

            $rows[] = $this->normalizeRow([
                'booking_date' => $this->parseDate($this->first($data, ['buchungstag', 'buchungsdatum', 'datum'])),
                'value_date' => $this->parseDate($this->first($data, ['wertstellung', 'valuta'])),
                'amount' => $amount,
                'currency' => strtoupper((string) ($this->first($data, ['waehrung', 'wahrung', 'currency']) ?: 'EUR')),
                'counterparty_name' => $counterpartyName,
                'counterparty_iban' => $this->first($data, [
                    'iban',
                    'ibanauftraggeberempfanger',
                    'ibanauftraggeberempfaenger',
                    'ibanbegunstigterzahlungspflichtiger',
                    'ibanbeguenstigterzahlungspflichtiger',
                    'kontonummeriban',
                ]),
                'purpose' => $purpose,
                'end_to_end_id' => $this->first($data, ['endtoendid', 'mandatsreferenz']),
                'bank_reference' => $this->first($data, ['kundenreferenz', 'referenz', 'bankreferenz']),
                'raw' => $data,
            ]);
        }

        fclose($handle);

        return $rows;
    }

    private function parseMt940(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, ':61:')) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'statement' => substr($line, 4),
                    'description' => '',
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if (str_starts_with($line, ':86:')) {
                $current['description'] .= ' ' . substr($line, 4);
                continue;
            }

            if (preg_match('/^:\d{2}[A-Z]?:/', $line)) {
                continue;
            }

            $current['description'] .= ' ' . $line;
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        $rows = [];

        foreach ($entries as $entry) {
            $statement = (string) $entry['statement'];
            $description = $this->normalizeMt940Description((string) $entry['description']);

            if (! preg_match('/^(?<date>\d{6})(?:\d{4})?(?<sign>R?[CD])(?<amount>\d+[,.]\d{0,2})/i', $statement, $matches)) {
                continue;
            }

            $amount = $this->decimal($matches['amount']);
            if ($amount <= 0) {
                continue;
            }

            $sign = strtoupper($matches['sign']);
            $signedAmount = str_ends_with($sign, 'D') ? -1 * $amount : $amount;
            $bookingDate = $this->parseMt940Date($matches['date']);
            $counterpartyName = $this->mt940StructuredValue((string) $entry['description'], ['32', '33', '34'])
                ?: $this->guessCounterpartyFromPurpose($description);
            $counterpartyIban = $this->mt940Iban((string) $entry['description']);

            $rows[] = $this->normalizeRow([
                'booking_date' => $bookingDate,
                'value_date' => null,
                'amount' => $signedAmount,
                'currency' => 'EUR',
                'counterparty_name' => $counterpartyName,
                'counterparty_iban' => $counterpartyIban,
                'purpose' => $description,
                'end_to_end_id' => $this->mt940StructuredValue((string) $entry['description'], ['20', '21', '22']),
                'bank_reference' => $this->clean($statement, 255),
                'raw' => [
                    'statement' => $statement,
                    'description' => trim((string) $entry['description']),
                ],
            ]);
        }

        return $rows;
    }

    private function normalizeRow(array $row): array
    {
        $amount = round((float) $row['amount'], 2);

        return [
            'booking_date' => $row['booking_date'] ?: now()->toDateString(),
            'value_date' => $row['value_date'] ?: null,
            'amount' => $amount,
            'currency' => substr(strtoupper((string) ($row['currency'] ?: 'EUR')), 0, 3),
            'direction' => $amount >= 0 ? 'credit' : 'debit',
            'counterparty_name' => $this->clean($row['counterparty_name'] ?? null, 255),
            'counterparty_iban' => $this->clean($row['counterparty_iban'] ?? null, 255),
            'purpose' => $this->clean($row['purpose'] ?? null, 2000),
            'end_to_end_id' => $this->clean($row['end_to_end_id'] ?? null, 255),
            'bank_reference' => $this->clean($row['bank_reference'] ?? null, 255),
            'raw' => $row['raw'] ?? [],
        ];
    }

    public function fingerprint(int $tenantId, int $accountId, array $row): string
    {
        return hash('sha256', implode('|', [
            $tenantId,
            $accountId,
            $row['booking_date'],
            number_format((float) $row['amount'], 2, '.', ''),
            $row['currency'],
            Str::lower((string) ($row['counterparty_iban'] ?? '')),
            Str::lower((string) ($row['counterparty_name'] ?? '')),
            Str::lower((string) ($row['purpose'] ?? '')),
            Str::lower((string) ($row['end_to_end_id'] ?? '')),
            Str::lower((string) ($row['bank_reference'] ?? '')),
        ]));
    }

    private function text(DOMXPath $xpath, string $query, DOMElement $context): ?string
    {
        $node = $xpath->query($query, $context)->item(0);
        return $node ? trim($node->textContent) : null;
    }

    private function texts(DOMXPath $xpath, string $query, DOMElement $context): ?string
    {
        $nodes = $xpath->query($query, $context);
        $parts = [];

        foreach ($nodes ?: [] as $node) {
            $value = trim($node->textContent);
            if ($value === '' || in_array($value, $parts, true)) {
                continue;
            }

            $parts[] = $value;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function currency(DOMXPath $xpath, string $query, DOMElement $context): string
    {
        $node = $xpath->query($query, $context)->item(0);

        if ($node instanceof DOMElement && $node->hasAttribute('Ccy')) {
            return strtoupper($node->getAttribute('Ccy'));
        }

        return 'EUR';
    }

    private function dateFromNode(DOMXPath $xpath, string $query, DOMElement $context): ?string
    {
        $node = $xpath->query($query, $context)->item(0);
        if (! $node instanceof DOMElement) {
            return null;
        }

        $date = $this->text($xpath, './*[local-name()="Dt"]', $node)
            ?: $this->text($xpath, './*[local-name()="DtTm"]', $node)
            ?: trim($node->textContent);

        return $this->parseDate($date);
    }

    private function detectDelimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 4096);
        $counts = collect([';' => substr_count($sample, ';'), ',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t")]);

        return (string) $counts->sortDesc()->keys()->first();
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = Str::ascii(Str::lower($value));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function csvAmount(array $data): ?float
    {
        $amount = $this->first($data, ['betrag', 'umsatz', 'amount']);
        if ($amount !== null && $amount !== '') {
            return $this->decimal((string) $amount);
        }

        $credit = $this->first($data, ['haben', 'gutschrift', 'einnahme']);
        if ($credit !== null && $credit !== '') {
            return abs($this->decimal((string) $credit));
        }

        $debit = $this->first($data, ['soll', 'lastschrift', 'ausgabe']);
        if ($debit !== null && $debit !== '') {
            return -1 * abs($this->decimal((string) $debit));
        }

        return null;
    }

    private function first(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && trim((string) $data[$key]) !== '') {
                return trim((string) $data[$key]);
            }
        }

        return null;
    }

    private function combine(array $data, array $keys): ?string
    {
        $parts = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);
            if ($value === '' || in_array($value, $parts, true)) {
                continue;
            }

            $parts[] = $value;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function decimal(?string $value): float
    {
        $value = trim((string) $value);
        $value = str_replace(["\xc2\xa0", ' ', "'"], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return round((float) preg_replace('/[^0-9.\-]/', '', $value), 2);
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $candidates = [$value];
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $matches)) {
            $candidates[] = $matches[0];
        }
        if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{2,4}/', $value, $matches)) {
            $candidates[] = $matches[0];
        }
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}/', $value, $matches)) {
            $candidates[] = $matches[0];
        }

        foreach (array_unique($candidates) as $candidate) {
            foreach (['Y-m-d', 'Y-m-d\TH:i:s', 'd.m.Y', 'd.m.y', 'd/m/Y', 'd/m/y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $candidate)->toDateString();
                } catch (\Throwable) {
                    //
                }
            }
        }

        foreach ($candidates as $candidate) {
            try {
                return Carbon::parse($candidate)->toDateString();
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }

    private function parseMt940Date(string $value): ?string
    {
        $year = (int) substr($value, 0, 2);
        $prefix = $year >= 70 ? '19' : '20';

        return $this->parseDate($prefix . substr($value, 0, 2) . '-' . substr($value, 2, 2) . '-' . substr($value, 4, 2));
    }

    private function normalizeMt940Description(string $value): ?string
    {
        $value = preg_replace('/\?\d{2}/', ' ', $value) ?? $value;
        $value = str_replace(['<', '>'], ' ', $value);

        return $this->clean($value, 2000);
    }

    private function mt940StructuredValue(string $value, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (preg_match('/\?' . preg_quote($key, '/') . '(?<value>.*?)(?=\?\d{2}|$)/s', $value, $matches)) {
                $result = $this->clean($matches['value'], 255);

                if ($result !== null && ! preg_match('/^[A-Z]{2}\d{2}/i', $result)) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function mt940Iban(string $value): ?string
    {
        if (preg_match('/[A-Z]{2}\d{2}[A-Z0-9]{10,30}/i', str_replace(' ', '', $value), $matches)) {
            return strtoupper($matches[0]);
        }

        return null;
    }

    private function guessCounterpartyFromPurpose(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['EREF+', 'SVWZ+', 'KREF+', 'MREF+', 'IBAN+', 'BIC+'] as $marker) {
            $position = stripos($value, $marker);
            if ($position !== false) {
                $value = trim(substr($value, 0, $position));
                break;
            }
        }

        return $this->clean($value, 120);
    }

    private function clean(?string $value, int $limit): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return $value === '' ? null : Str::limit($value, $limit, '');
    }
}
