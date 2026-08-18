<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EventBookingBillingService
{
    public function __construct(
        private readonly InvoicePdfService $invoicePdfService,
        private readonly TenantMailConfigurator $tenantMailConfigurator,
    ) {
    }

    public function createInvoiceForBooking(EventBooking $booking, array $answers, Event $event, Tenant $tenant): Invoice
    {
        $booking->loadMissing('invoice');

        if ($booking->invoice) {
            return $booking->invoice;
        }

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'document_type' => 'invoice',
            'income_account_id' => $this->resolveDefaultIncomeAccountId($tenant->id, 'event'),
            'recipient_type' => 'free',
            'recipient_name' => $booking->booker_name,
            'recipient_email' => $booking->booker_email,
            'recipient_street' => trim((string) ($answers['street'] ?? '')),
            'recipient_zip' => trim((string) ($answers['zip'] ?? '')),
            'recipient_city' => trim((string) ($answers['city'] ?? '')),
            'recipient_country' => trim((string) ($answers['country'] ?? '')) ?: 'Deutschland',
            'invoice_number' => Invoice::generateDocumentNumber('invoice', $tenant->id),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'open',
            'intro_text' => $this->buildIntroText($booking, $event),
            'payment_text' => $this->buildPaymentText($tenant),
            'closing_text' => "Mit freundlichen Gruessen\n" . ($tenant->name ?? 'Euer Verein'),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $this->buildItemDescription($event),
            'details' => 'Buchungsnummer: ' . $booking->booking_reference,
            'quantity' => $booking->participant_count,
            'unit' => 'Teilnehmer',
            'unit_price' => $booking->price_per_person,
        ]);

        if ((float) ($booking->voucher_discount_amount ?? 0) > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Gutschein angerechnet',
                'details' => 'Buchungsnummer: ' . $booking->booking_reference,
                'quantity' => 1,
                'unit' => 'Gutschein',
                'unit_price' => -1 * (float) $booking->voucher_discount_amount,
            ]);
        }

        $booking->update(['invoice_id' => $invoice->id]);

        return $invoice->fresh(['items']);
    }

    public function sendInvoiceMail(Invoice $invoice, EventBooking $booking, Event $event, Tenant $tenant): void
    {
        if (blank($invoice->recipient_email)) {
            return;
        }

        $this->tenantMailConfigurator->apply($tenant);

        $subject = 'Rechnung ' . $invoice->invoice_number . ' fuer ' . $event->title;
        $body = $this->buildMailBody($invoice, $booking, $event, $tenant);
        $pdfBinary = $this->invoicePdfService->render($invoice, $tenant);
        $pdfName = 'Rechnung_' . $invoice->invoice_number . '.pdf';

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;

        try {
            Mail::send('mail.layout', [
                'body' => $body,
                'tenant' => $tenant,
            ], function ($mail) use ($invoice, $subject, $fromAddress, $fromName, $replyToAddress, $tenant, $pdfBinary, $pdfName) {
                $mail->to($invoice->recipient_email, $invoice->recipient_name ?: null)
                    ->subject($subject)
                    ->from($fromAddress, $fromName)
                    ->attachData($pdfBinary, $pdfName, ['mime' => 'application/pdf']);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?? $fromName);
                }
            });

            TemplateDispatchLog::create([
                'tenant_id' => $tenant->id,
                'template_id' => null,
                'created_by' => null,
                'channel' => 'mail',
                'action' => 'event_booking_invoice_sent',
                'recipient_type' => 'event_booking',
                'recipient_name' => $invoice->recipient_name,
                'recipient_reference' => $invoice->recipient_email,
                'subject' => $subject,
                'message_excerpt' => 'Rechnung ' . $invoice->invoice_number . ' fuer Event-Buchung ' . $booking->booking_reference,
                'dispatched_at' => now(),
                'meta' => [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'booking_reference' => $booking->booking_reference,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Rechnungsmail fuer Event-Buchung fehlgeschlagen', [
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
                'email' => $invoice->recipient_email,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function buildItemDescription(Event $event): string
    {
        $date = $event->start?->format('d.m.Y');

        return trim('Event-Buchung ' . $event->title . ($date ? ' am ' . $date : ''));
    }

    private function buildIntroText(EventBooking $booking, Event $event): string
    {
        return "Guten Tag,\n\nvielen Dank fuer eure Buchung fuer das Event \"" . $event->title . "\". Anbei senden wir euch die Rechnung zur Anmeldung mit der Buchungsnummer " . $booking->booking_reference . '.';
    }

    private function buildPaymentText(Tenant $tenant): string
    {
        $accountLine = blank($tenant->iban)
            ? 'Bitte ueberweist den Rechnungsbetrag innerhalb von 14 Tagen auf das bei euch hinterlegte Vereinskonto.'
            : 'Bitte ueberweist den Rechnungsbetrag innerhalb von 14 Tagen auf folgendes Konto: ' . $tenant->iban . '.';

        return $accountLine . ' Bitte gebt als Verwendungszweck die Rechnungsnummer an.';
    }

    private function buildMailBody(Invoice $invoice, EventBooking $booking, Event $event, Tenant $tenant): string
    {
        $amount = number_format($invoice->getTotal(), 2, ',', '.') . ' ' . strtoupper($event->currency ?: 'EUR');
        $dueDate = optional($invoice->due_date)->format('d.m.Y') ?: 'baldmoeglich';

        return '<p>Guten Tag,</p>'
            . '<p>vielen Dank fuer eure Buchung fuer <strong>' . e($event->title) . '</strong>.</p>'
            . '<p>Anbei findet ihr die Rechnung <strong>' . e($invoice->invoice_number) . '</strong> ueber <strong>' . e($amount) . '</strong>.</p>'
            . '<p>Bitte ueberweist den Betrag bis zum <strong>' . e($dueDate) . '</strong> und gebt dabei die Rechnungsnummer <strong>' . e($invoice->invoice_number) . '</strong> als Verwendungszweck an.</p>'
            . '<p>Die Buchungsnummer lautet <strong>' . e($booking->booking_reference) . '</strong>.</p>'
            . '<p>Viele Gruesse<br>' . e($tenant->name ?? 'Euer Verein') . '</p>';
    }

    private function resolveDefaultIncomeAccountId(int $tenantId, string $context = 'general'): ?int
    {
        $incomeAccounts = Account::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'einnahme')
            ->where('active', true)
            ->orderBy('number')
            ->orderBy('name')
            ->get();

        if ($incomeAccounts->isEmpty()) {
            return null;
        }

        $preferredNames = match ($context) {
            'event' => ['Veranstaltungen', 'Veranstaltungserlöse', 'Veranstaltungserloese', 'Teilnahmegebühren', 'Teilnahmegebuehren', 'Event-Einnahmen'],
            default => ['Sonstige Erlöse', 'Sonstige Erloese', 'Dienstleistungen', 'Erlöse', 'Erloese'],
        };

        foreach ($preferredNames as $name) {
            $match = $incomeAccounts->first(fn (Account $account) => mb_strtolower((string) $account->name) === mb_strtolower($name));

            if ($match) {
                return $match->id;
            }
        }

        return $incomeAccounts->first()?->id;
    }
}
