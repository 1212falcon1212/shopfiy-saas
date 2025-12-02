<?php

namespace App\Support\Invoice\Ubl;

use App\Models\Customer;
use App\Models\TrendyolOrder;
use Illuminate\Support\Str;

class EArchiveBuilder
{
    public function buildFromTrendyolOrder(TrendyolOrder $order): array
    {
        $customer = $order->customer; // Zunapro müşterisi (satıcı)

        $sellerName = $this->getSellerName($customer);
        $sellerVkn  = $this->getSellerVkn($customer);
        $sellerTaxOffice = $this->getSellerTaxOffice($customer);
        $sellerCity = $this->getSellerCity($customer);
        $sellerDistrict = $this->getSellerDistrict($customer);
        $sellerPostalCode = $this->getSellerPostalCode($customer);
        $sellerStreet = $this->getSellerStreet($customer);

        $buyerName = trim(($order->customer_first_name ?? '') . ' ' . ($order->customer_last_name ?? '')); // Alıcı ad soyad
        $buyerEmail = $order->customer_email ?? '';
        $buyerTckn = data_get($order->invoice_address, 'tcIdentityNumber', '');

        $currency = $order->currency_code ?: 'TRY';
        $issueDate = optional($order->order_date)->format('Y-m-d') ?: date('Y-m-d');
        $issueTime = date('H:i:s');
        $uuid = (string) Str::uuid();
        $invoiceId = $order->order_number ?: ('ZNP-' . date('Ymd') . '-' . $order->id);

        $linesXml = '';
        $lineIndex = 0;
        $totalWithoutTax = 0.0;
        $totalTax = 0.0;

        // KDV oranlarına göre gruplanmış alt toplamlar
        $taxSubtotals = [];

        foreach ($order->items as $item) {
            $lineIndex++;
            $qty = (float) ($item->quantity ?? 1);
            $unitPrice = (float) ($item->amount ?? $item->price ?? 0);
            $vatRate = (float) ($item->vat_rate ?? 0);

            $lineNet = round($unitPrice * $qty, 2);
            $lineTax = round($lineNet * ($vatRate / 100), 2);

            $totalWithoutTax += $lineNet;
            $totalTax += $lineTax;

            if (!isset($taxSubtotals[(string)$vatRate])) {
                $taxSubtotals[(string)$vatRate] = [
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                ];
            }
            $taxSubtotals[(string)$vatRate]['taxable_amount'] += $lineNet;
            $taxSubtotals[(string)$vatRate]['tax_amount'] += $lineTax;

            $itemName = $item->product_name ?: ($item->merchant_sku ?: 'Ürün');

            $linesXml .= '\n        <cac:InvoiceLine>'
                ."\n            <cbc:ID>{$lineIndex}</cbc:ID>"
                ."\n            <cbc:InvoicedQuantity unitCode=\"C62\">" . number_format($qty, 2, '.', '') . "</cbc:InvoicedQuantity>"
                ."\n            <cbc:LineExtensionAmount currencyID=\"{$currency}\">" . number_format($lineNet, 2, '.', '') . "</cbc:LineExtensionAmount>"
                ."\n            <cac:TaxTotal>"
                ."\n                <cbc:TaxAmount currencyID=\"{$currency}\">" . number_format($lineTax, 2, '.', '') . "</cbc:TaxAmount>"
                ."\n                <cac:TaxSubtotal>"
                    ."\n                    <cbc:TaxableAmount currencyID=\"{$currency}\">" . number_format($lineNet, 2, '.', '') . "</cbc:TaxableAmount>"
                    ."\n                    <cbc:TaxAmount currencyID=\"{$currency}\">" . number_format($lineTax, 2, '.', '') . "</cbc:TaxAmount>"
                    ."\n                    <cbc:Percent>" . number_format($vatRate, 0, '.', '') . "</cbc:Percent>"
                    ."\n                    <cac:TaxCategory>\n                        <cac:TaxScheme>\n                            <cbc:Name>GERÇEK USULDE KATMA DEĞER VERGİSİ</cbc:Name>\n                            <cbc:TaxTypeCode>0015</cbc:TaxTypeCode>\n                        </cac:TaxScheme>\n                    </cac:TaxCategory>"
                ."\n                </cac:TaxSubtotal>"
            ."\n            </cac:TaxTotal>"
            ."\n            <cac:Item>\n                <cbc:Name>" . htmlspecialchars($itemName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</cbc:Name>" 
            .(!empty($item->merchant_sku) ? "\n                <cac:SellersItemIdentification><cbc:ID>".htmlspecialchars($item->merchant_sku, ENT_XML1 | ENT_COMPAT, 'UTF-8')."</cbc:ID></cac:SellersItemIdentification>" : '')
            ."\n            </cac:Item>"
            ."\n            <cac:Price>\n                <cbc:PriceAmount currencyID=\"{$currency}\">" . number_format($unitPrice, 2, '.', '') . "</cbc:PriceAmount>\n            </cac:Price>"
            ."\n        </cac:InvoiceLine>";
        }

        $taxExclusive = round($totalWithoutTax, 2);
        $taxInclusive = round($totalWithoutTax + $totalTax, 2);
        $payable = $taxInclusive; // İskonto yok varsayımı

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'\n<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
            .' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
            .' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"'
            .' xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"'
            .' xmlns:ds="http://www.w3.org/2000/09/xmldsig#"'
            .' xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'\n    <ext:UBLExtensions>'
            .'\n      <ext:UBLExtension>'
            .'\n        <ext:ExtensionContent>'
            .'\n          <ds:Signature>'
            .'\n            <ds:SignedInfo>'
            .'\n              <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
            .'\n              <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>'
            .'\n              <ds:Reference URI="">'
            .'\n                <ds:Transforms>'
            .'\n                  <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>'
            .'\n                </ds:Transforms>'
            .'\n                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'
            .'\n                <ds:DigestValue/>'
            .'\n              </ds:Reference>'
            .'\n            </ds:SignedInfo>'
            .'\n            <ds:SignatureValue/>'
            .'\n            <ds:KeyInfo>'
            .'\n              <ds:KeyValue><ds:RSAKeyValue><ds:Modulus/><ds:Exponent/></ds:RSAKeyValue></ds:KeyValue>'
            .'\n              <ds:X509Data><ds:X509Certificate/></ds:X509Data>'
            .'\n            </ds:KeyInfo>'
            .'\n            <ds:Object>'
            .'\n              <xades:QualifyingProperties Target="#Signature_'.$uuid.'">'
            .'\n                <xades:SignedProperties Id="SignedProperties_'.$uuid.'">'
            .'\n                  <xades:SignedSignatureProperties>'
            .'\n                    <xades:SigningTime>'.date('c').'</xades:SigningTime>'
            .'\n                  </xades:SignedSignatureProperties>'
            .'\n                </xades:SignedProperties>'
            .'\n              </xades:QualifyingProperties>'
            .'\n            </ds:Object>'
            .'\n          </ds:Signature>'
            .'\n        </ext:ExtensionContent>'
            .'\n      </ext:UBLExtension>'
            .'\n    </ext:UBLExtensions>'
            .'\n    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>'
            .'\n    <cbc:CustomizationID>TR1.2</cbc:CustomizationID>'
            .'\n    <cbc:ProfileID>EARSIVFATURA</cbc:ProfileID>'
            .'\n    <cbc:ID>'.htmlspecialchars($invoiceId, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:ID>'
            .'\n    <cbc:CopyIndicator>false</cbc:CopyIndicator>'
            .'\n    <cbc:UUID>'.$uuid.'</cbc:UUID>'
            .'\n    <cbc:IssueDate>'.$issueDate.'</cbc:IssueDate>'
            .'\n    <cbc:IssueTime>'.$issueTime.'</cbc:IssueTime>'
            .'\n    <cbc:InvoiceTypeCode>SATIS</cbc:InvoiceTypeCode>'
            .'\n    <cbc:Note>İrsaliye yerine geçer.</cbc:Note>'
            .'\n    <cbc:DocumentCurrencyCode>'.$currency.'</cbc:DocumentCurrencyCode>'
            .'\n    <cbc:LineCountNumeric>'.count($order->items).'</cbc:LineCountNumeric>'
            .($order->order_number ? ('\n    <cac:OrderReference><cbc:ID>'.htmlspecialchars($order->order_number, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:ID>'.($order->order_date ? '<cbc:IssueDate>'.$issueDate.'</cbc:IssueDate>' : '').'</cac:OrderReference>') : '')
            // Gönderim şekli: ELEKTRONIK
            .'\n    <cac:AdditionalDocumentReference>'
            .'\n        <cbc:ID>'.(string) Str::uuid().'</cbc:ID>'
            .'\n        <cbc:IssueDate>'.$issueDate.'</cbc:IssueDate>'
            .'\n        <cbc:DocumentTypeCode>GONDERIM_SEKLI</cbc:DocumentTypeCode>'
            .'\n        <cbc:DocumentType>ELEKTRONIK</cbc:DocumentType>'
            .'\n    </cac:AdditionalDocumentReference>'
            // İnternet satış bilgileri (opsiyonel) – Trendyol için ödeme aracı platform
            .'\n    <cac:AdditionalDocumentReference>'
            .'\n        <cbc:ID>INTERNET_SATIS</cbc:ID>'
            .'\n        <cbc:IssueDate>'.$issueDate.'</cbc:IssueDate>'
            .'\n        <cbc:DocumentTypeCode>ODEME_SEKLI</cbc:DocumentTypeCode>'
            .'\n        <cbc:DocumentType>ODEMEARACISI</cbc:DocumentType>'
            .'\n        <cac:IssuerParty>'
            .'\n            <cbc:WebsiteURI>https://www.trendyol.com</cbc:WebsiteURI>'
            .'\n            <cac:PartyIdentification><cbc:ID/></cac:PartyIdentification>'
            .'\n            <cac:PartyName><cbc:Name/></cac:PartyName>'
            .'\n            <cac:PostalAddress><cbc:CitySubdivisionName/><cbc:CityName/><cac:Country><cbc:Name/></cac:Country></cac:PostalAddress>'
            .'\n        </cac:IssuerParty>'
            .'\n    </cac:AdditionalDocumentReference>'
            .'\n    <cac:AccountingSupplierParty>'
            .'\n        <cac:Party>'
            .'\n            <cac:PartyIdentification><cbc:ID schemeID="VKN">'.htmlspecialchars($sellerVkn, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:ID></cac:PartyIdentification>'
            .'\n            <cac:PartyName><cbc:Name>'.htmlspecialchars($sellerName, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:Name></cac:PartyName>'
            .'\n            <cac:PostalAddress>'
            .'\n                <cbc:StreetName>'.htmlspecialchars($sellerStreet, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:StreetName>'
            .'\n                <cbc:CitySubdivisionName>'.htmlspecialchars($sellerDistrict, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:CitySubdivisionName>'
            .'\n                <cbc:CityName>'.htmlspecialchars($sellerCity, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:CityName>'
            .'\n                <cbc:PostalZone>'.htmlspecialchars($sellerPostalCode, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:PostalZone>'
            .'\n                <cac:Country><cbc:Name>Türkiye</cbc:Name></cac:Country>'
            .'\n            </cac:PostalAddress>'
            .'\n            <cac:PartyTaxScheme><cac:TaxScheme><cbc:Name>'.htmlspecialchars($sellerTaxOffice, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:Name></cac:TaxScheme></cac:PartyTaxScheme>'
            .'\n        </cac:Party>'
            .'\n    </cac:AccountingSupplierParty>'
            .'\n    <cac:AccountingCustomerParty>'
            .'\n        <cac:Party>'
            .($buyerTckn ? ('\n            <cac:PartyIdentification><cbc:ID schemeID="TCKN">'.htmlspecialchars($buyerTckn, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:ID></cac:PartyIdentification>') : '')
            .'\n            <cac:PartyName><cbc:Name>'.htmlspecialchars($buyerName ?: 'Alıcı', ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:Name></cac:PartyName>'
            .'\n            <cac:Contact><cbc:ElectronicMail>'.htmlspecialchars($buyerEmail, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:ElectronicMail></cac:Contact>'
            .'\n        </cac:Party>'
            .'\n    </cac:AccountingCustomerParty>'
            .'\n    <cac:TaxTotal>'
            .'\n        <cbc:TaxAmount currencyID="'.$currency.'">'.number_format($totalTax, 2, '.', '').'</cbc:TaxAmount>'
            .(function() use ($taxSubtotals, $currency) {
                $xml = '';
                foreach ($taxSubtotals as $rate => $amounts) {
                    $xml .= '\n        <cac:TaxSubtotal>'
                        .'\n            <cbc:TaxableAmount currencyID="'.$currency.'">'.number_format($amounts['taxable_amount'], 2, '.', '').'</cbc:TaxableAmount>'
                        .'\n            <cbc:TaxAmount currencyID="'.$currency.'">'.number_format($amounts['tax_amount'], 2, '.', '').'</cbc:TaxAmount>'
                        .'\n            <cbc:Percent>'.$rate.'</cbc:Percent>'
                        .'\n            <cac:TaxCategory><cac:TaxScheme><cbc:Name>GERÇEK USULDE KATMA DEĞER VERGİSİ</cbc:Name><cbc:TaxTypeCode>0015</cbc:TaxTypeCode></cac:TaxScheme></cac:TaxCategory>'
                        .'\n        </cac:TaxSubtotal>';
                }
                return $xml;
            })()
            .'\n    </cac:TaxTotal>'
            .'\n    <cac:LegalMonetaryTotal>'
            .'\n        <cbc:LineExtensionAmount currencyID="'.$currency.'">'.number_format($taxExclusive, 2, '.', '').'</cbc:LineExtensionAmount>'
            .'\n        <cbc:TaxExclusiveAmount currencyID="'.$currency.'">'.number_format($taxExclusive, 2, '.', '').'</cbc:TaxExclusiveAmount>'
            .'\n        <cbc:TaxInclusiveAmount currencyID="'.$currency.'">'.number_format($taxInclusive, 2, '.', '').'</cbc:TaxInclusiveAmount>'
            .'\n        <cbc:AllowanceTotalAmount currencyID="'.$currency.'">0.00</cbc:AllowanceTotalAmount>'
            .'\n        <cbc:PayableAmount currencyID="'.$currency.'">'.number_format($payable, 2, '.', '').'</cbc:PayableAmount>'
            .'\n    </cac:LegalMonetaryTotal>'
            // Kargo/teslimat
            .'\n    <cac:Delivery>'
            .'\n        <cbc:ActualDeliveryDate>'.$issueDate.'</cbc:ActualDeliveryDate>'
            .($order->cargo_provider_name ? ('\n        <cac:CarrierParty>'
                .'<cac:PartyName><cbc:Name>'.htmlspecialchars($order->cargo_provider_name, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</cbc:Name></cac:PartyName>'
                .'<cac:PostalAddress><cbc:CitySubdivisionName/><cbc:CityName/><cac:Country><cbc:Name/></cac:Country></cac:PostalAddress>'
            .'</cac:CarrierParty>') : '')
            .'\n    </cac:Delivery>'
            ."\n    {$linesXml}"
            .'\n</Invoice>';

        return [
            'document_uuid' => $uuid,
            'xml' => $xml,
            'source_label' => 'Zunapro',
            'target_email' => $buyerEmail,
        ];
    }

    private function getSellerName(Customer $customer): string
    {
        return $customer->getMeta('company_name') ?? $customer->name ?? 'SATICI';
    }

    private function getSellerVkn(Customer $customer): string
    {
        return (string) ($customer->getMeta('tax_number') ?? '');
    }

    private function getSellerTaxOffice(Customer $customer): string
    {
        return (string) ($customer->getMeta('tax_office') ?? '');
    }

    private function getSellerCity(Customer $customer): string
    {
        return (string) ($customer->getMeta('city') ?? '');
    }

    private function getSellerDistrict(Customer $customer): string
    {
        return (string) ($customer->getMeta('district') ?? '');
    }

    private function getSellerPostalCode(Customer $customer): string
    {
        return (string) ($customer->getMeta('postal_code') ?? '');
    }

    private function getSellerStreet(Customer $customer): string
    {
        return (string) ($customer->getMeta('address') ?? '');
    }

    private function guessVatRate(TrendyolOrder $order): float
    {
        $rate = 0.0;
        foreach ($order->items as $item) {
            if (!empty($item->vat_rate)) { $rate = (float) $item->vat_rate; break; }
        }
        return $rate;
    }
}


