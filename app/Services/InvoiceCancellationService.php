<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MemberCreditApplication;
use App\Models\EventBooking;
use App\Models\TemplateDispatchLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceCancellationService
{
    public function __construct(
        private readonly TenantMailConfigurator $tenantMailConfigurator,
    ) {
    }

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
        $notificationInvoice = null;

        DB::transaction(function () use ($invoice, $reason, &$notificationInvoice) {
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

            $notificationInvoice = $invoice->fresh(['tenant', 'eventBookings.event']);
        });

        if ($notificationInvoice) {
            $this->sendCancellationMail($notificationInvoice);
        }
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

    private function sendCancellationMail(Invoice $invoice): void
    {
        if (blank($invoice->recipient_email) || ! $invoice->tenant) {
            return;
        }

        $tenant = $invoice->tenant;
        $eventBooking = $invoice->eventBookings->first();
        $event = $eventBooking?->event;

        $this->tenantMailConfigurator->apply($tenant);

        $subject = 'Storno zur Rechnung ' . $invoice->invoice_number;
        $body = $this->buildCancellationMailBody($invoice, $eventBooking, $event, $tenant);
        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;

        try {
            Mail::send('mail.layout', [
                'body' => $body,
                'tenant' => $tenant,
            ], function ($mail) use ($invoice, $subject, $fromAddress, $fromName, $replyToAddress, $tenant) {
                $mail->to($invoice->recipient_email, $invoice->recipient_name ?: null)
                    ->subject($subject)
                    ->from($fromAddress, $fromName);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?? $fromName);
                }
            });

            TemplateDispatchLog::create([
                'tenant_id' => $tenant->id,
                'template_id' => null,
                'created_by' => Auth::id(),
                'channel' => 'mail',
                'action' => 'invoice_cancellation_sent',
                'recipient_type' => $eventBooking ? 'event_booking' : 'invoice',
                'recipient_name' => $invoice->recipient_name,
                'recipient_reference' => $invoice->recipient_email,
                'subject' => $subject,
                'message_excerpt' => 'Storno-Information zur Rechnung ' . $invoice->invoice_number,
                'dispatched_at' => now(),
                'meta' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'booking_id' => $eventBooking?->id,
                    'booking_reference' => $eventBooking?->booking_reference,
                    'event_id' => $event?->id,
                    'event_title' => $event?->title,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Storno-Mail fuer Rechnung fehlgeschlagen', [
                'invoice_id' => $invoice->id,
                'email' => $invoice->recipient_email,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function buildCancellationMailBody(Invoice $invoice, ?EventBooking $booking, $event, $tenant): string
    {
        $reason = trim((string) $invoice->cancellation_reason);
        $reasonLine = $reason !== ''
            ? '<p><strong>Grund:</strong> ' . e($reason) . '</p>'
            : '';

        $eventLine = $event
            ? '<p>Die Rechnung gehörte zur Veranstaltung <strong>' . e($event->title) . '</strong>'
                . ($booking ? ' mit der Buchungsnummer <strong>' . e($booking->booking_reference) . '</strong>' : '')
                . '.</p>'
            : '';

        return '<p>Guten Tag,</p>'
            . '<p>die Rechnung <strong>' . e($invoice->invoice_number) . '</strong> wurde storniert.</p>'
            . $eventLine
            . $reasonLine
            . '<p>Für diese Rechnung ist keine Zahlung mehr erforderlich. Falls bereits eine Zahlung erfolgt ist oder Fragen offen sind, meldet euch bitte direkt bei uns.</p>'
            . '<p>Viele Gruesse<br>' . e($tenant->name ?? 'Euer Verein') . '</p>';
    }
}
