<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Voucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Mpdf\QrCode\Output;
use Mpdf\QrCode\QrCode;

class VoucherPdfService
{
    public function render(Voucher $voucher, Tenant $tenant): string
    {
        $templatePath = $tenant->voucher_template_path && Storage::disk('public')->exists($tenant->voucher_template_path)
            ? storage_path('app/public/' . $tenant->voucher_template_path)
            : null;
        [$pageWidthMm, $voucherHeightMm, $templateRatio] = $this->pageSize($tenant);
        $dedicationHeightMm = filled($voucher->dedication_message) ? 34.0 : 0.0;
        $usesExternalCodePanel = $templatePath !== null;
        $templateWidthMm = $pageWidthMm;
        $templateHeightMm = $voucherHeightMm;
        $templateLeftMm = 0.0;

        if ($usesExternalCodePanel) {
            $pageWidthMm = 297.0;
            $pageHeightMm = 210.0;
            $voucherHeightMm = min($voucherHeightMm, 168.0);
            $templateHeightMm = $voucherHeightMm;
            $templateWidthMm = min($pageWidthMm, round($templateHeightMm * $templateRatio, 2));
            $templateLeftMm = round(($pageWidthMm - $templateWidthMm) / 2, 2);
            $dedicationHeightMm = $pageHeightMm - $voucherHeightMm;
        } elseif ($dedicationHeightMm > 0) {
            $pageWidthMm = 297.0;
            $pageHeightMm = 210.0;
            $voucherHeightMm = min($voucherHeightMm, 176.0);
            $templateHeightMm = $voucherHeightMm;
            $dedicationHeightMm = $pageHeightMm - $voucherHeightMm;
        } else {
            $pageHeightMm = $voucherHeightMm;
        }

        $qrCodeDataUri = null;

        if ($tenant->voucher_show_qr ?? true) {
            $qrCode = new QrCode($this->verificationUrl($voucher));
            $qrPng = (new Output\Png())->output($qrCode, 160, [255, 255, 255], [15, 23, 42]);
            $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($qrPng);
        }

        return Pdf::loadView('vouchers.pdf', [
            'voucher' => $voucher,
            'tenant' => $tenant,
            'templatePath' => $templatePath,
            'qrCodeDataUri' => $qrCodeDataUri,
            'positionClass' => $this->positionClass($tenant->voucher_code_position ?: 'bottom-right'),
            'codeColor' => $this->safeColor($tenant->voucher_code_color ?: '#0f172a'),
            'pageWidthMm' => $pageWidthMm,
            'pageHeightMm' => $pageHeightMm,
            'voucherHeightMm' => $voucherHeightMm,
            'dedicationHeightMm' => $dedicationHeightMm,
            'usesExternalCodePanel' => $usesExternalCodePanel,
            'templateWidthMm' => $templateWidthMm,
            'templateHeightMm' => $templateHeightMm,
            'templateLeftMm' => $templateLeftMm,
        ])
            ->setPaper([0, 0, $this->mmToPoint($pageWidthMm), $this->mmToPoint($pageHeightMm)])
            ->output();
    }

    public function filename(Voucher $voucher): string
    {
        return 'Gutschein_' . preg_replace('/[^A-Z0-9-]/', '_', $voucher->code) . '.pdf';
    }

    public function verificationUrl(Voucher $voucher): string
    {
        return route('vouchers.check', ['code' => $voucher->code]);
    }

    private function positionClass(string $position): string
    {
        return in_array($position, ['bottom-right', 'bottom-left', 'top-right', 'top-left'], true)
            ? $position
            : 'bottom-right';
    }

    private function safeColor(string $color): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#0f172a';
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function pageSize(Tenant $tenant): array
    {
        $templateWidth = (int) $tenant->voucher_template_width;
        $templateHeight = (int) $tenant->voucher_template_height;

        if (($templateWidth <= 0 || $templateHeight <= 0)
            && $tenant->voucher_template_path
            && Storage::disk('public')->exists($tenant->voucher_template_path)) {
            $dimensions = @getimagesize(Storage::disk('public')->path($tenant->voucher_template_path));
            $templateWidth = (int) ($dimensions[0] ?? 0);
            $templateHeight = (int) ($dimensions[1] ?? 0);
        }

        if ($templateWidth > 0 && $templateHeight > 0) {
            $ratio = max(0.5, min(3.0, $templateWidth / $templateHeight));
            $width = 297.0;

            return [$width, round($width / $ratio, 2), $ratio];
        }

        return [297.0, 210.0, 297.0 / 210.0];
    }

    private function mmToPoint(float $millimeters): float
    {
        return $millimeters * 72 / 25.4;
    }
}
