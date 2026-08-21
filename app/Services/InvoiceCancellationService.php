<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MemberCreditApplication;
use App\Models\EventBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InvoiceCancellationService
{
    public function canCancelAutomatically(Invoice $invoice): bool
    {
        if (! $invoice->isInvoice()) {
            return false;
        }

        if ($invoice->status === 'storniert') {
            return true;
        }

        return ! $invoice->isPaid() && ! $invoice->payments()->exists();
    }

    public function cancel(Invoice $invoice, ?string $reason = null): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice->loadMissing(['items', 'eventBookings']);

            if ($invoice->status === 'storniert') {
                if (blank($invoice->cancellation_reason) && filled($reason)) {
                    $invoice->forceFill([
                        'cancellation_reason' => $reason,
                        'cancelled_at' => $invoice->cancelled_at ?: now(),
                        'cancelled_by' => $invoice->cancelled_by ?: Auth::id(),
                    ])->save();
                }

                $this->syncEventBookingPaymentStatus($invoice);

                return;
            }

            $applications = MemberCreditApplication::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('invoice_id', $invoice->id)
                ->with('credit')
                ->lockForUpdate()
                ->get();

            foreach ($applications as $application) {
                if ($application->credit) {
                    $application->credit->forceFill([
                        'remaining_amount' => round((float) $application->credit->remaining_amount + (float) $application->amount, 2),
                    ])->save();
                }
            }

            if ($applications->isNotEmpty()) {
                MemberCreditApplication::query()
                    ->where('tenant_id', $invoice->tenant_id)
                    ->where('invoice_id', $invoice->id)
                    ->delete();

                $invoice->items()
                    ->where('description', 'Verrechnetes Guthaben')
                    ->where('unit', 'Guthaben')
                    ->delete();
            }

            $invoice->forceFill([
                'status' => 'storniert',
                'cancellation_reason' => filled($reason) ? trim($reason) : 'Automatisch storniert.',
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'paid_at' => null,
                'sepa_exported_at' => null,
                'sepa_sequence_type' => null,
                'last_sepa_run_id' => null,
            ])->save();

            $this->syncEventBookingPaymentStatus($invoice);
        });
    }

    public function cancelForEventBookingIfPossible(EventBooking $booking): bool
    {
        $booking->loadMissing('invoice');

        if (! $booking->invoice || ! $this->canCancelAutomatically($booking->invoice)) {
            return false;
        }

        $this->cancel($booking->invoice, 'Automatisch storniert, weil die verknüpfte Veranstaltungsbuchung storniert wurde.');

        return true;
    }

    private function syncEventBookingPaymentStatus(Invoice $invoice): void
    {
        $paymentStatus = match ($invoice->status) {
            'paid' => 'paid',
            'storniert' => 'cancelled',
            default => 'open',
        };

        $invoice->eventBookings()->update([
            'payment_status' => $paymentStatus,
        ]);
    }
}
