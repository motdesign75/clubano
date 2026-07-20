<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class SepaExportService
{
    public function generatePain008(Tenant $tenant, Collection $invoices, CarbonInterface $collectionDate, string $sequenceType = 'OOFF'): string
    {
        if (blank($tenant->creditor_identifier) || blank($tenant->iban) || blank($tenant->bic)) {
            throw new RuntimeException('Für den SEPA-Export fehlen Gläubiger-ID, IBAN oder BIC in den Vereinsdaten.');
        }

        if ($invoices->isEmpty()) {
            throw new RuntimeException('Es wurden keine geeigneten Rechnungen für den SEPA-Export gefunden.');
        }

        $messageId = 'CLUBANO-' . now()->format('YmdHis');
        $paymentInfoId = 'PI-' . now()->format('YmdHis');
        $controlSum = number_format($invoices->sum(fn (Invoice $invoice) => $invoice->getTotal()), 2, '.', '');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $document = $dom->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.008.001.02', 'Document');
        $dom->appendChild($document);

        $customerDebitTransferInitiation = $document->appendChild($dom->createElement('CstmrDrctDbtInitn'));
        $groupHeader = $customerDebitTransferInitiation->appendChild($dom->createElement('GrpHdr'));
        $this->appendTextElement($dom, $groupHeader, 'MsgId', $messageId);
        $this->appendTextElement($dom, $groupHeader, 'CreDtTm', now()->format('Y-m-d\TH:i:s'));
        $this->appendTextElement($dom, $groupHeader, 'NbOfTxs', (string) $invoices->count());
        $this->appendTextElement($dom, $groupHeader, 'CtrlSum', $controlSum);

        $initiatingParty = $groupHeader->appendChild($dom->createElement('InitgPty'));
        $this->appendTextElement($dom, $initiatingParty, 'Nm', $this->sanitizeText($tenant->name));

        $paymentInformation = $customerDebitTransferInitiation->appendChild($dom->createElement('PmtInf'));
        $this->appendTextElement($dom, $paymentInformation, 'PmtInfId', $paymentInfoId);
        $this->appendTextElement($dom, $paymentInformation, 'PmtMtd', 'DD');
        $this->appendTextElement($dom, $paymentInformation, 'NbOfTxs', (string) $invoices->count());
        $this->appendTextElement($dom, $paymentInformation, 'CtrlSum', $controlSum);

        $paymentTypeInformation = $paymentInformation->appendChild($dom->createElement('PmtTpInf'));
        $serviceLevel = $paymentTypeInformation->appendChild($dom->createElement('SvcLvl'));
        $this->appendTextElement($dom, $serviceLevel, 'Cd', 'SEPA');
        $localInstrument = $paymentTypeInformation->appendChild($dom->createElement('LclInstrm'));
        $this->appendTextElement($dom, $localInstrument, 'Cd', 'CORE');
        $this->appendTextElement($dom, $paymentTypeInformation, 'SeqTp', $sequenceType);

        $this->appendTextElement($dom, $paymentInformation, 'ReqdColltnDt', $collectionDate->format('Y-m-d'));

        $creditor = $paymentInformation->appendChild($dom->createElement('Cdtr'));
        $this->appendTextElement($dom, $creditor, 'Nm', $this->sanitizeText($tenant->name));

        $creditorAccount = $paymentInformation->appendChild($dom->createElement('CdtrAcct'));
        $creditorAccountId = $creditorAccount->appendChild($dom->createElement('Id'));
        $this->appendTextElement($dom, $creditorAccountId, 'IBAN', $this->normalizeIban($tenant->iban));

        $creditorAgent = $paymentInformation->appendChild($dom->createElement('CdtrAgt'));
        $financialInstitutionId = $creditorAgent->appendChild($dom->createElement('FinInstnId'));
        $this->appendTextElement($dom, $financialInstitutionId, 'BIC', strtoupper(trim((string) $tenant->bic)));

        $this->appendTextElement($dom, $paymentInformation, 'ChrgBr', 'SLEV');

        $creditorSchemeId = $paymentInformation->appendChild($dom->createElement('CdtrSchmeId'));
        $creditorSchemeIdId = $creditorSchemeId->appendChild($dom->createElement('Id'));
        $privateId = $creditorSchemeIdId->appendChild($dom->createElement('PrvtId'));
        $other = $privateId->appendChild($dom->createElement('Othr'));
        $this->appendTextElement($dom, $other, 'Id', strtoupper(trim((string) $tenant->creditor_identifier)));
        $schemeName = $other->appendChild($dom->createElement('SchmeNm'));
        $this->appendTextElement($dom, $schemeName, 'Prtry', 'SEPA');

        foreach ($invoices as $invoice) {
            $this->appendTransaction($dom, $paymentInformation, $invoice);
        }

        return $dom->saveXML();
    }

    private function appendTransaction(DOMDocument $dom, \DOMElement $paymentInformation, Invoice $invoice): void
    {
        $member = $invoice->member;

        if (!$member || $member->payment_method !== 'sepa_lastschrift') {
            throw new RuntimeException("Rechnung {$invoice->invoice_number} ist nicht für SEPA-Lastschrift geeignet.");
        }

        $transaction = $paymentInformation->appendChild($dom->createElement('DrctDbtTxInf'));
        $paymentId = $transaction->appendChild($dom->createElement('PmtId'));
        $this->appendTextElement($dom, $paymentId, 'EndToEndId', $invoice->invoice_number);

        $instructedAmount = $transaction->appendChild($dom->createElement('InstdAmt', number_format($invoice->getTotal(), 2, '.', '')));
        $instructedAmount->setAttribute('Ccy', 'EUR');

        $directDebitTransaction = $transaction->appendChild($dom->createElement('DrctDbtTx'));
        $mandateRelatedInformation = $directDebitTransaction->appendChild($dom->createElement('MndtRltdInf'));
        $this->appendTextElement($dom, $mandateRelatedInformation, 'MndtId', $this->sanitizeText($member->sepa_mandate_reference));
        $this->appendTextElement($dom, $mandateRelatedInformation, 'DtOfSgntr', optional($member->sepa_signed_at)->format('Y-m-d'));

        $debtorAgent = $transaction->appendChild($dom->createElement('DbtrAgt'));
        $financialInstitutionId = $debtorAgent->appendChild($dom->createElement('FinInstnId'));
        $this->appendTextElement($dom, $financialInstitutionId, 'BIC', strtoupper(trim((string) $member->bic)));

        $debtor = $transaction->appendChild($dom->createElement('Dbtr'));
        $this->appendTextElement($dom, $debtor, 'Nm', $this->sanitizeText($member->sepa_account_holder ?: $invoice->getRecipientDisplayName()));

        $debtorAccount = $transaction->appendChild($dom->createElement('DbtrAcct'));
        $debtorAccountId = $debtorAccount->appendChild($dom->createElement('Id'));
        $this->appendTextElement($dom, $debtorAccountId, 'IBAN', $this->normalizeIban($member->iban));

        $remittanceInformation = $transaction->appendChild($dom->createElement('RmtInf'));
        $this->appendTextElement($dom, $remittanceInformation, 'Ustrd', $this->sanitizeText('Beitrag ' . $invoice->invoice_number));
    }

    private function appendTextElement(DOMDocument $dom, \DOMElement $parent, string $name, ?string $value): \DOMElement
    {
        $element = $dom->createElement($name);
        $element->nodeValue = $value ?? '';
        $parent->appendChild($element);

        return $element;
    }

    private function normalizeIban(?string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $iban));
    }

    private function sanitizeText(?string $value): string
    {
        $clean = trim((string) $value);
        $clean = Str::of($clean)->ascii()->replaceMatches('/[^A-Za-z0-9 .,:+?\\/-]/', ' ')->squish()->value();

        return mb_substr($clean, 0, 140);
    }
}
