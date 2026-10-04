<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use RuntimeException;

class XRechnungService
{
    public function render(Invoice $invoice, Tenant $tenant): string
    {
        $invoice->loadMissing(['items']);
        $this->assertExportable($invoice, $tenant);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $document->appendChild($root);

        $this->appendText($document, $root, 'cbc:CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0');
        $this->appendText($document, $root, 'cbc:ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $this->appendText($document, $root, 'cbc:ID', $invoice->invoice_number);
        $this->appendText($document, $root, 'cbc:IssueDate', $invoice->invoice_date->toDateString());
        $this->appendText($document, $root, 'cbc:DueDate', $invoice->due_date?->toDateString() ?: $invoice->invoice_date->toDateString());
        $this->appendText($document, $root, 'cbc:InvoiceTypeCode', '380');
        $this->appendText($document, $root, 'cbc:DocumentCurrencyCode', 'EUR');
        $this->appendText($document, $root, 'cbc:BuyerReference', trim((string) $invoice->e_invoice_buyer_reference));

        if (filled($invoice->e_invoice_order_reference)) {
            $orderReference = $this->appendElement($document, $root, 'cac:OrderReference');
            $this->appendText($document, $orderReference, 'cbc:ID', trim((string) $invoice->e_invoice_order_reference));
        }

        $this->appendSupplierParty($document, $root, $tenant);
        $this->appendCustomerParty($document, $root, $invoice);
        $this->appendPaymentMeans($document, $root, $invoice, $tenant);
        $this->appendDocumentAllowance($document, $root, $invoice);
        $this->appendTaxTotal($document, $root, $invoice);
        $this->appendMonetaryTotal($document, $root, $invoice);
        $this->appendInvoiceLines($document, $root, $invoice);

        return $document->saveXML();
    }

    public function validationErrors(Invoice $invoice, Tenant $tenant): array
    {
        $invoice->loadMissing(['items']);
        $errors = [];

        if (! $invoice->isInvoice()) {
            $errors[] = 'XRechnung kann nur fuer Rechnungen erzeugt werden, nicht fuer Angebote.';
        }

        if (! $invoice->e_invoice_enabled) {
            $errors[] = 'E-Rechnung ist fuer diese Rechnung nicht aktiviert.';
        }

        if (blank($invoice->e_invoice_buyer_reference)) {
            $errors[] = 'Leitweg-ID / Käuferreferenz fehlt.';
        }

        foreach ([
            'Vereinsname' => $tenant->name,
            'Vereinsadresse' => $tenant->address,
            'Vereins-PLZ' => $tenant->zip,
            'Vereinsort' => $tenant->city,
            'Vereins-E-Mail' => $tenant->email,
            'IBAN' => $tenant->iban,
            'Rechnungsempfänger' => $invoice->getRecipientDisplayName(),
            'Empfängerstraße' => $invoice->recipient_street,
            'Empfänger-PLZ' => $invoice->recipient_zip,
            'Empfängerort' => $invoice->recipient_city,
        ] as $label => $value) {
            if (blank($value)) {
                $errors[] = $label . ' fehlt.';
            }
        }

        if ($invoice->items->isEmpty()) {
            $errors[] = 'Mindestens eine Rechnungsposition ist erforderlich.';
        }

        return $errors;
    }

    private function assertExportable(Invoice $invoice, Tenant $tenant): void
    {
        $errors = $this->validationErrors($invoice, $tenant);

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }
    }

    private function appendSupplierParty(DOMDocument $document, DOMElement $root, Tenant $tenant): void
    {
        $supplier = $this->appendElement($document, $root, 'cac:AccountingSupplierParty');
        $party = $this->appendElement($document, $supplier, 'cac:Party');
        $this->appendEndpoint($document, $party, $tenant->email);
        $this->appendPostalAddress($document, $party, $tenant->address, $tenant->zip, $tenant->city, 'DE');

        $taxScheme = $this->appendElement($document, $this->appendElement($document, $party, 'cac:PartyTaxScheme'), 'cac:TaxScheme');
        $this->appendText($document, $taxScheme, 'cbc:ID', 'VAT');

        $legalEntity = $this->appendElement($document, $party, 'cac:PartyLegalEntity');
        $this->appendText($document, $legalEntity, 'cbc:RegistrationName', $tenant->name);

        if (filled($tenant->register_number)) {
            $this->appendText($document, $legalEntity, 'cbc:CompanyID', $tenant->register_number);
        }

        $contact = $this->appendElement($document, $party, 'cac:Contact');
        $this->appendText($document, $contact, 'cbc:Name', $tenant->name);
        $this->appendText($document, $contact, 'cbc:ElectronicMail', $tenant->email);
    }

    private function appendCustomerParty(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $customer = $this->appendElement($document, $root, 'cac:AccountingCustomerParty');
        $party = $this->appendElement($document, $customer, 'cac:Party');
        $this->appendEndpoint($document, $party, $invoice->recipient_email ?: $invoice->e_invoice_buyer_reference);
        $this->appendPostalAddress($document, $party, $invoice->recipient_street, $invoice->recipient_zip, $invoice->recipient_city, $this->countryCode($invoice->recipient_country));

        $legalEntity = $this->appendElement($document, $party, 'cac:PartyLegalEntity');
        $this->appendText($document, $legalEntity, 'cbc:RegistrationName', $invoice->getRecipientDisplayName());
    }

    private function appendPaymentMeans(DOMDocument $document, DOMElement $root, Invoice $invoice, Tenant $tenant): void
    {
        $paymentMeans = $this->appendElement($document, $root, 'cac:PaymentMeans');
        $this->appendText($document, $paymentMeans, 'cbc:PaymentMeansCode', '58');
        $this->appendText($document, $paymentMeans, 'cbc:PaymentID', 'Rechnung ' . $invoice->invoice_number);

        $account = $this->appendElement($document, $paymentMeans, 'cac:PayeeFinancialAccount');
        $this->appendText($document, $account, 'cbc:ID', preg_replace('/\s+/', '', (string) $tenant->iban));
        $this->appendText($document, $account, 'cbc:Name', $tenant->name);

        if (filled($tenant->bic)) {
            $branch = $this->appendElement($document, $account, 'cac:FinancialInstitutionBranch');
            $this->appendText($document, $branch, 'cbc:ID', preg_replace('/\s+/', '', (string) $tenant->bic));
        }
    }

    private function appendTaxTotal(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $taxRate = (float) ($invoice->tax_rate ?? 0);
        $taxableAmount = $invoice->getNetTotal();
        $taxAmount = $invoice->getTaxAmount();

        $taxTotal = $this->appendElement($document, $root, 'cac:TaxTotal');
        $this->appendAmount($document, $taxTotal, 'cbc:TaxAmount', $taxAmount);

        $taxSubtotal = $this->appendElement($document, $taxTotal, 'cac:TaxSubtotal');
        $this->appendAmount($document, $taxSubtotal, 'cbc:TaxableAmount', $taxableAmount);
        $this->appendAmount($document, $taxSubtotal, 'cbc:TaxAmount', $taxAmount);

        $category = $this->appendElement($document, $taxSubtotal, 'cac:TaxCategory');
        $this->appendText($document, $category, 'cbc:ID', $taxRate > 0 ? 'S' : 'E');
        $this->appendText($document, $category, 'cbc:Percent', $this->decimal($taxRate));

        if ($taxRate <= 0) {
            $this->appendText($document, $category, 'cbc:TaxExemptionReason', 'Steuerbefreit oder nicht steuerbar');
        }

        $scheme = $this->appendElement($document, $category, 'cac:TaxScheme');
        $this->appendText($document, $scheme, 'cbc:ID', 'VAT');
    }

    private function appendDocumentAllowance(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $discountAmount = $invoice->getDiscountAmount();

        if ($discountAmount <= 0) {
            return;
        }

        $allowance = $this->appendElement($document, $root, 'cac:AllowanceCharge');
        $this->appendText($document, $allowance, 'cbc:ChargeIndicator', 'false');
        $this->appendText($document, $allowance, 'cbc:AllowanceChargeReason', 'Rabatt');
        $this->appendAmount($document, $allowance, 'cbc:Amount', $discountAmount);

        $taxCategory = $this->appendElement($document, $allowance, 'cac:TaxCategory');
        $taxRate = (float) ($invoice->tax_rate ?? 0);
        $this->appendText($document, $taxCategory, 'cbc:ID', $taxRate > 0 ? 'S' : 'E');
        $this->appendText($document, $taxCategory, 'cbc:Percent', $this->decimal($taxRate));
        $this->appendText($document, $this->appendElement($document, $taxCategory, 'cac:TaxScheme'), 'cbc:ID', 'VAT');
    }

    private function appendMonetaryTotal(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $total = $invoice->getTotal();
        $monetaryTotal = $this->appendElement($document, $root, 'cac:LegalMonetaryTotal');
        $this->appendAmount($document, $monetaryTotal, 'cbc:LineExtensionAmount', $invoice->getSubtotal());
        $this->appendAmount($document, $monetaryTotal, 'cbc:TaxExclusiveAmount', $invoice->getNetTotal());
        $this->appendAmount($document, $monetaryTotal, 'cbc:TaxInclusiveAmount', $total);
        $this->appendAmount($document, $monetaryTotal, 'cbc:AllowanceTotalAmount', $invoice->getDiscountAmount());
        $this->appendAmount($document, $monetaryTotal, 'cbc:PrepaidAmount', 0);
        $this->appendAmount($document, $monetaryTotal, 'cbc:PayableAmount', $total);
    }

    private function appendInvoiceLines(DOMDocument $document, DOMElement $root, Invoice $invoice): void
    {
        $taxRate = (float) ($invoice->tax_rate ?? 0);

        $invoice->items->values()->each(function (InvoiceItem $item, int $index) use ($document, $root, $taxRate) {
            $line = $this->appendElement($document, $root, 'cac:InvoiceLine');
            $this->appendText($document, $line, 'cbc:ID', (string) ($index + 1));

            $quantity = $this->appendText($document, $line, 'cbc:InvoicedQuantity', $this->decimal((float) $item->quantity));
            $quantity->setAttribute('unitCode', $this->unitCode((string) $item->unit));

            $this->appendAmount($document, $line, 'cbc:LineExtensionAmount', (float) $item->quantity * (float) $item->unit_price);

            $itemNode = $this->appendElement($document, $line, 'cac:Item');
            if (filled($item->details)) {
                $this->appendText($document, $itemNode, 'cbc:Description', $item->details);
            }
            $this->appendText($document, $itemNode, 'cbc:Name', $item->description);

            $taxCategory = $this->appendElement($document, $itemNode, 'cac:ClassifiedTaxCategory');
            $this->appendText($document, $taxCategory, 'cbc:ID', $taxRate > 0 ? 'S' : 'E');
            $this->appendText($document, $taxCategory, 'cbc:Percent', $this->decimal($taxRate));
            $this->appendText($document, $this->appendElement($document, $taxCategory, 'cac:TaxScheme'), 'cbc:ID', 'VAT');

            $price = $this->appendElement($document, $line, 'cac:Price');
            $this->appendAmount($document, $price, 'cbc:PriceAmount', (float) $item->unit_price);
        });
    }

    private function appendEndpoint(DOMDocument $document, DOMElement $party, ?string $value): void
    {
        $endpoint = $this->appendText($document, $party, 'cbc:EndpointID', $value ?: 'unknown@example.invalid');
        $endpoint->setAttribute('schemeID', str_contains((string) $value, '@') ? 'EM' : '0204');
    }

    private function appendPostalAddress(DOMDocument $document, DOMElement $party, ?string $street, ?string $zip, ?string $city, string $countryCode): void
    {
        $address = $this->appendElement($document, $party, 'cac:PostalAddress');
        $this->appendText($document, $address, 'cbc:StreetName', $street ?: '-');
        $this->appendText($document, $address, 'cbc:CityName', $city ?: '-');
        $this->appendText($document, $address, 'cbc:PostalZone', $zip ?: '-');

        $country = $this->appendElement($document, $address, 'cac:Country');
        $this->appendText($document, $country, 'cbc:IdentificationCode', $countryCode);
    }

    private function appendAmount(DOMDocument $document, DOMElement $parent, string $name, float $amount): DOMElement
    {
        $node = $this->appendText($document, $parent, $name, number_format(round($amount, 2), 2, '.', ''));
        $node->setAttribute('currencyID', 'EUR');

        return $node;
    }

    private function appendText(DOMDocument $document, DOMElement $parent, string $name, ?string $value): DOMElement
    {
        $node = $this->appendElement($document, $parent, $name);
        $node->appendChild($document->createTextNode((string) $value));

        return $node;
    }

    private function appendElement(DOMDocument $document, DOMElement $parent, string $name): DOMElement
    {
        $namespace = str_starts_with($name, 'cac:')
            ? 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2'
            : (str_starts_with($name, 'cbc:')
                ? 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2'
                : null);

        $node = $namespace
            ? $document->createElementNS($namespace, $name)
            : $document->createElement($name);

        $parent->appendChild($node);

        return $node;
    }

    private function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function countryCode(?string $country): string
    {
        $normalized = Str::of((string) $country)->lower()->ascii()->trim()->toString();

        return match ($normalized) {
            '', 'de', 'deutschland', 'germany' => 'DE',
            'at', 'oesterreich', 'osterreich', 'austria' => 'AT',
            'ch', 'schweiz', 'switzerland' => 'CH',
            default => strtoupper(substr($normalized, 0, 2)) ?: 'DE',
        };
    }

    private function unitCode(string $unit): string
    {
        $normalized = Str::of($unit)->lower()->ascii()->trim()->toString();

        return match ($normalized) {
            'stuck', 'stueck', 'stk', 'stk.', 'person', 'personen', '' => 'H87',
            'stunde', 'stunden', 'std', 'h' => 'HUR',
            'tag', 'tage' => 'DAY',
            'monat', 'monate' => 'MON',
            'jahr', 'jahre' => 'ANN',
            default => 'H87',
        };
    }
}
