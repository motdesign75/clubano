<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Tenant;
use Mpdf\Mpdf;

class InvoicePdfService
{
    public function render(Invoice $invoice, Tenant $tenant): string
    {
        $invoice->loadMissing(['member', 'contact', 'items']);

        $letterheadImagePath = null;
        $usesLetterhead = false;

        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->SetTitle($invoice->getDocumentLabel() . ' ' . $invoice->invoice_number);
        $mpdf->SetAuthor($tenant->name ?? 'Clubano');
        $mpdf->SetAutoPageBreak(true, 34);

        if ($tenant->use_letterhead && $tenant->pdf_template) {
            $path = storage_path('app/public/' . $tenant->pdf_template);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (is_file($path)) {
                if ($extension === 'pdf') {
                    try {
                        $mpdf->SetDocTemplate($path, true);
                        $usesLetterhead = true;
                    } catch (\Throwable $exception) {
                        // Fallback: Dokument wird ohne PDF-Briefbogen erzeugt.
                    }
                } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                    $letterheadImagePath = $path;
                    $usesLetterhead = true;
                }
            }
        }

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'paymentQrPayload' => $this->buildPaymentQrPayload($invoice, $tenant),
            'letterheadImagePath' => $letterheadImagePath,
            'showLetterheadImage' => !empty($letterheadImagePath),
            'usesLetterhead' => $usesLetterhead,
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function buildPaymentQrPayload(Invoice $invoice, Tenant $tenant): ?string
    {
        if (!$invoice->isInvoice()
            || blank($tenant->iban)
            || blank($tenant->name)
            || !class_exists(\Mpdf\QrCode\QrCode::class)
            || !class_exists(\Mpdf\QrCode\Output\Mpdf::class)
        ) {
            return null;
        }

        $iban = strtoupper(preg_replace('/\s+/', '', (string) $tenant->iban));
        $bic = strtoupper(preg_replace('/\s+/', '', (string) $tenant->bic));
        $recipient = $this->sanitizeEpcQrText($tenant->name, 70);
        $purpose = $this->sanitizeEpcQrText('Rechnung ' . $invoice->invoice_number, 140);
        $amount = 'EUR' . number_format($invoice->getTotal(), 2, '.', '');

        return implode("\n", [
            'BCD',
            '002',
            '1',
            'SCT',
            $bic,
            $recipient,
            $iban,
            $amount,
            '',
            '',
            $purpose,
            '',
        ]);
    }

    private function sanitizeEpcQrText(?string $value, int $limit): string
    {
        $value = trim((string) $value);
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_substr($value, 0, $limit);
    }
}
