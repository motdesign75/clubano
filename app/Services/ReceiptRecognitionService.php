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
            'recognized_vendor' => $this->vendor($readableName),
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
        $value = preg_replace('/(?<!\d)(\d{4})[-_. ](\d{1,2})[-_. ](\d{1,2})(?!\d)/', ' ', $value) ?? $value;
        $value = preg_replace('/(?<!\d)(\d{1,2})[-_. ](\d{1,2})[-_. ](\d{4})(?!\d)/', ' ', $value) ?? $value;

        if (! preg_match_all('/(?<!\d)(\d{1,3}(?:[.\s]\d{3})*|\d+)[,.](\d{2})(?:\s?(eur|euro|€))?(?!\d)/i', $value, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $match = collect($matches)->first(fn (array $candidate) => filled($candidate[3] ?? null)) ?? end($matches);

        $number = str_replace(['.', ' '], '', $match[1]) . '.' . $match[2];

        return round((float) $number, 2);
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

    private function vendor(string $value): ?string
    {
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

    private function invoiceNumber(string $value): ?string
    {
        if (preg_match('/(?:rechnung|invoice|beleg|nr|no)[-_.:\s]*([a-z0-9-]{3,30})/i', $value, $match)) {
            return Str::upper($match[1]);
        }

        return null;
    }
}
