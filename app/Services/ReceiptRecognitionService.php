<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ReceiptRecognitionService
{
    /**
     * Lightweight recognition without an external OCR service. It safely extracts
     * common date, amount and invoice hints from the filename, then lets the user verify.
     */
    public function fromUpload(UploadedFile $file): array
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $readableName = Str::of($name)
            ->replace(['_', '-'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        $text = trim($this->textFromFile($file));
        $source = $text !== '' ? 'OCR/Text' : 'Dateiname';
        $searchableText = trim($readableName . ' ' . $text);

        return [
            'recognized_amount' => $this->amount($searchableText),
            'recognized_currency' => 'EUR',
            'recognized_date' => $this->date($searchableText),
            'recognized_vendor' => $this->vendor($readableName, $text),
            'recognized_invoice_number' => $this->invoiceNumber($searchableText),
            'recognition_source' => $source,
            'recognition_notes' => $source === 'OCR/Text'
                ? 'Automatischer Vorschlag aus dem Belegtext. Bitte vor dem Buchen prüfen.'
                : 'Automatischer Vorschlag aus dem Dateinamen. Für Foto-Erkennung muss OCR auf dem Server installiert sein.',
        ];
    }

    private function textFromFile(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return '';
        }

        $mime = (string) $file->getMimeType();
        $finder = new ExecutableFinder();

        if (str_starts_with($mime, 'image/')) {
            $tesseract = $finder->find('tesseract');
            if (! $tesseract) {
                return '';
            }

            return $this->runProcess([$tesseract, $path, 'stdout', '-l', 'deu+eng', '--psm', '6']);
        }

        if ($mime === 'application/pdf') {
            $pdftotext = $finder->find('pdftotext');
            if (! $pdftotext) {
                return '';
            }

            return $this->runProcess([$pdftotext, '-layout', $path, '-']);
        }

        return '';
    }

    /**
     * @param array<int, string> $command
     */
    private function runProcess(array $command): string
    {
        try {
            $process = new Process($command);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful() ? Str::limit($process->getOutput(), 5000, '') : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function amount(string $value): ?float
    {
        $value = $this->normalizeOcrText($value);
        $value = $this->removeNonAmountNoise($value);

        if (blank($value)) {
            return null;
        }

        $candidates = collect();
        $lines = preg_split('/\R+/', $value) ?: [$value];

        foreach ($lines as $index => $line) {
            $context = trim(($lines[$index - 1] ?? '') . ' ' . $line . ' ' . ($lines[$index + 1] ?? ''));

            foreach ($this->amountCandidates($line) as $candidate) {
                $candidates->push($candidate + [
                    'score' => $this->amountScore($context, $candidate),
                    'position' => $index,
                ]);
            }
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $best = $candidates
            ->sortBy([
                ['score', 'desc'],
                ['amount', 'desc'],
                ['position', 'desc'],
            ])
            ->first();

        return $best ? round((float) $best['amount'], 2) : null;
    }

    private function date(string $value): ?string
    {
        $patterns = [
            '/(?<!\d)(\d{4})[-_. ](\d{1,2})[-_. ](\d{1,2})(?!\d)/',
            '/(?<!\d)(\d{1,2})[-_. ](\d{1,2})[-_. ](\d{4})(?!\d)/',
        ];

        foreach ($patterns as $index => $pattern) {
            if (! preg_match($pattern, $value, $match)) {
                continue;
            }

            try {
                return $index === 0
                    ? Carbon::create((int) $match[1], (int) $match[2], (int) $match[3])->toDateString()
                    : Carbon::create((int) $match[3], (int) $match[2], (int) $match[1])->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function vendor(string $value, string $text = ''): ?string
    {
        $textVendor = collect(preg_split('/\R+/', $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => strlen($line) >= 3 && strlen($line) <= 80)
            ->reject(fn (string $line) => preg_match('/\d{2,}|iban|bic|ust|steuer|summe|gesamt|betrag|datum|rechnung/i', $line))
            ->first();

        if (filled($textVendor)) {
            return Str::limit((string) $textVendor, 120, '');
        }

        $cleaned = Str::of($value)
            ->replaceMatches('/(?<!\d)(\d{4})[-_. ](\d{1,2})[-_. ](\d{1,2})(?!\d)/', ' ')
            ->replaceMatches('/(?<!\d)(\d{1,2})[-_. ](\d{1,2})[-_. ](\d{4})(?!\d)/', ' ')
            ->replaceMatches('/(?<!\d)(\d{1,3}(?:[.\s]\d{3})*|\d+)[,.](\d{2})(?:\s?(?:eur|euro|€))?(?!\d)/i', ' ')
            ->replaceMatches('/\b(rechnung|beleg|quittung|invoice|nr|no)\b/i', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim(' -_.')
            ->toString();

        return $cleaned !== '' ? Str::limit($cleaned, 120, '') : null;
    }

    private function normalizeOcrText(string $value): string
    {
        return Str::of($value)
            ->replace(["\t", "\u{00a0}"], ' ')
            ->replaceMatches('/(?<=\d)[oO](?=\d|\s?(?:eur|euro|€))/i', '0')
            ->replaceMatches('/(?<=\d)[lI](?=\d)/', '1')
            ->replaceMatches('/[^\S\r\n]+/', ' ')
            ->replaceMatches('/[^\S\r\n]*(\R)[^\S\r\n]*/', '$1')
            ->toString();
    }

    private function removeNonAmountNoise(string $value): string
    {
        $value = preg_replace('/(?<!\d)(\d{4})[-_. ](\d{1,2})[-_. ](\d{1,2})(?!\d)/', ' ', $value) ?? $value;
        $value = preg_replace('/(?<!\d)(\d{1,2})[-_. ](\d{1,2})[-_. ](\d{4})(?!\d)/', ' ', $value) ?? $value;
        $value = preg_replace('/\b[A-Z]{2}\d{2}(?:\s?\d{4}){2,7}\b/i', ' ', $value) ?? $value;

        return $value;
    }

    /**
     * @return array<int, array{amount: float, has_currency: bool, raw: string}>
     */
    private function amountCandidates(string $line): array
    {
        if (! preg_match_all('/(?<![\d\/])(\d{1,3}(?:[.\s]\d{3})*|\d+)[,.](\d{2})(?:\s?(eur|euro|€))?(?!\d)/i', $line, $matches, PREG_SET_ORDER)) {
            return [];
        }

        return collect($matches)
            ->map(function (array $match) {
                $number = str_replace(['.', ' '], '', $match[1]) . '.' . $match[2];

                return [
                    'amount' => (float) $number,
                    'has_currency' => filled($match[3] ?? null),
                    'raw' => $match[0],
                ];
            })
            ->filter(fn (array $candidate) => $candidate['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param array{amount: float, has_currency: bool, raw: string} $candidate
     */
    private function amountScore(string $context, array $candidate): int
    {
        $score = 10;
        $context = Str::lower($context);

        if ($candidate['has_currency']) {
            $score += 25;
        }

        if (preg_match('/\b(summe|gesamt|total|endbetrag|zu zahlen|rechnungsbetrag|betrag fällig|zahlbetrag|brutto)\b/u', $context)) {
            $score += 90;
        }

        if (preg_match('/\b(netto|mwst|ust|steuer|pfand|rabatt|skonto|gegeben|rückgeld|wechselgeld|anzahl|stück|stueck)\b/u', $context)) {
            $score -= 55;
        }

        if (preg_match('/\b(kartenzahlung|ec-karte|visa|mastercard|barzahlung|bezahlt)\b/u', $context)) {
            $score += 20;
        }

        if ($candidate['amount'] >= 100000) {
            $score -= 80;
        }

        return $score;
    }

    private function invoiceNumber(string $value): ?string
    {
        if (preg_match('/(?:rechnung|invoice|beleg|nr|no)[-_.:\s]*([a-z0-9-]{3,30})/i', $value, $match)) {
            return Str::upper($match[1]);
        }

        return null;
    }
}
