<?php

namespace App\Support\Invoice\Ubl;

use XMLWriter;

class UblBuilder
{
    private $writer;
    private $data;

    // XML içerikleri için güvenli kaçış
    private static function esc($v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    public function build(array $invoiceData): string
    {
        $uuid       = (string)($invoiceData['uuid'] ?? '');
        $currency   = (string)($invoiceData['currency'] ?? 'TRY');
        $lines      = [];

        // XML header.
        $lines[] = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

        // GİB-TR UBL Sıralamasına göre yeniden düzenlendi
        // 1. UBLExtensions
        $lines = array_merge($lines, $this->buildSignaturePlaceholder($uuid, (array)($invoiceData['supplier'] ?? [])));

        // 2. Temel Fatura Bilgileri
        $lines = array_merge($lines, $this->buildCoreInvoiceProperties($invoiceData));

        // 3. OrderReference
        $orderRef = (array)($invoiceData['order_reference'] ?? []);
        if (!empty($orderRef)) {
            $lines = array_merge($lines, $this->buildOrderReference($orderRef));
        }

        // 4. AdditionalDocumentReference (Gönderim Şekli ve İnternet Satış)
        $lines = array_merge($lines, $this->buildShippingDocumentReference($invoiceData));
        $internet = (array)($invoiceData['internet_sale'] ?? []);
        if (!empty($internet)) {
            $lines = array_merge($lines, $this->buildInternetSaleDocumentReference($internet, (string)($invoiceData['issue_date'] ?? date('Y-m-d'))));
        }

        // 5. Signature
        $lines = array_merge($lines, $this->buildSignatureBlock($uuid, (array)($invoiceData['supplier'] ?? [])));

        // 6. AccountingSupplierParty
        $lines = array_merge($lines, $this->buildSupplierParty((array)($invoiceData['supplier'] ?? [])));

        // 7. AccountingCustomerParty
        $lines = array_merge($lines, $this->buildCustomerParty((array)($invoiceData['customer'] ?? [])));

        // 8. Delivery (opsiyonel)
        if (!empty($invoiceData['delivery'])) {
            $lines = array_merge($lines, $this->buildDelivery((array)$invoiceData['delivery']));
        }

        // 9. AllowanceCharge
        $lines = array_merge($lines, $this->buildAllowanceCharge($currency));

        // 10. TaxTotal
        $lines = array_merge($lines, $this->buildTaxTotals((array)($invoiceData['tax_totals'] ?? []), $currency));

        // 11. LegalMonetaryTotal
        $lines = array_merge($lines, $this->buildLegalMonetaryTotal((array)($invoiceData['totals'] ?? []), $currency));

        // 12. InvoiceLine
        $lines = array_merge($lines, $this->buildInvoiceLines((array)($invoiceData['lines'] ?? []), $currency));

        $lines[] = '</Invoice>';
        $xmlString = implode("\n", $lines);
        return $xmlString;
    }

    private function buildCoreInvoiceProperties(array $data): array
    {
        $out = [];
        $profile   = strtoupper((string)($data['profile_id'] ?? 'TEMELFATURA'));
        $id        = (string)($data['id'] ?? 'INV-1');
        $uuid      = (string)($data['uuid'] ?? '');
        $issueDate = (string)($data['issue_date'] ?? date('Y-m-d'));
        $issueTime = (string)($data['issue_time'] ?? date('H:i:s'));
        $typeCode  = strtoupper((string)($data['type_code'] ?? 'SATIS'));
        $currency  = (string)($data['currency'] ?? 'TRY');
        $lineCount = (int) (is_countable($data['lines'] ?? null) ? count($data['lines']) : 0);

        $out[] = '  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>';
        $out[] = '  <cbc:CustomizationID>TR1.2</cbc:CustomizationID>';
        $out[] = '  <cbc:ProfileID>' . $this->escape($profile) . '</cbc:ProfileID>';
        $out[] = '  <cbc:ID>' . $this->escape($id) . '</cbc:ID>';
        $out[] = '  <cbc:CopyIndicator>false</cbc:CopyIndicator>';
        $out[] = '  <cbc:UUID>' . $this->escape($uuid) . '</cbc:UUID>';
        $out[] = '  <cbc:IssueDate>' . $this->escape($issueDate) . '</cbc:IssueDate>';
        $out[] = '  <cbc:IssueTime>' . $this->normalizeTime($issueTime) . '</cbc:IssueTime>';
        $out[] = '  <cbc:InvoiceTypeCode>' . $this->escape($typeCode) . '</cbc:InvoiceTypeCode>';

        foreach ((array)($data['notes'] ?? []) as $note) {
            if ($note !== '' && $note !== null) {
                $out[] = '  <cbc:Note>' . $this->escape((string)$note) . '</cbc:Note>';
            }
        }

        $out[] = '  <cbc:DocumentCurrencyCode>' . $this->escape($currency) . '</cbc:DocumentCurrencyCode>';
        $out[] = '  <cbc:LineCountNumeric>' . $this->escape((string)$lineCount) . '</cbc:LineCountNumeric>';

        return $out;
    }

    private function buildOrderReference(array $orderRef): array
    {
        $out = [];
        if (!empty($orderRef['id'])) {
            $out[] = '  <cac:OrderReference>';
            $out[] = '    <cbc:ID>' . $this->escape((string)$orderRef['id']) . '</cbc:ID>';
            if (!empty($orderRef['issue_date'])) {
                $out[] = '    <cbc:IssueDate>' . $this->escape((string)$orderRef['issue_date']) . '</cbc:IssueDate>';
            }
            $out[] = '  </cac:OrderReference>';
        }
        return $out;
    }

    private function buildShippingDocumentReference(array $data): array
    {
        $out = [];
        $profile = strtoupper((string)($data['profile_id'] ?? 'TEMELFATURA'));
        $issueDate = (string)($data['issue_date'] ?? date('Y-m-d'));

        if ($profile === 'EARSIVFATURA') {
            $out[] = '  <cac:AdditionalDocumentReference>';
            $out[] = '    <cbc:ID>' . $this->escape(uniqid()) . '</cbc:ID>';
            $out[] = '    <cbc:IssueDate>' . $this->escape($issueDate) . '</cbc:IssueDate>';
            $out[] = '    <cbc:DocumentTypeCode>GONDERIM_SEKLI</cbc:DocumentTypeCode>';
            $out[] = '    <cbc:DocumentType>ELEKTRONIK</cbc:DocumentType>';
            $out[] = '  </cac:AdditionalDocumentReference>';
        }
        return $out;
    }
    
    private function buildInternetSaleDocumentReference(array $internet, string $issueDate): array
    {
        $webAddress = $internet['webAddress'] ?? '';
        $paymentType = $internet['paymentType'] ?? 'DIGER';
        $lines = [];
        $lines[] = '  <cac:AdditionalDocumentReference>';
        $lines[] = '    <cbc:ID>INTERNET_SATIS</cbc:ID>';
        $lines[] = '    <cbc:IssueDate>'.self::esc($issueDate).'</cbc:IssueDate>';
        $lines[] = '    <cbc:DocumentTypeCode>ODEME_SEKLI</cbc:DocumentTypeCode>';
        $lines[] = '    <cbc:DocumentType>'.self::esc($paymentType).'</cbc:DocumentType>';
        $lines[] = '    <cac:IssuerParty>';
        $lines[] = '      <cbc:WebsiteURI>'.self::esc($webAddress).'</cbc:WebsiteURI>';
        $lines[] = '      <cac:PartyIdentification><cbc:ID/></cac:PartyIdentification>';
        $lines[] = '      <cac:PartyName><cbc:Name/></cac:PartyName>';
        $lines[] = '      <cac:PostalAddress>';
        $lines[] = '        <cbc:CitySubdivisionName/>';
        $lines[] = '        <cbc:CityName/>';
        $lines[] = '        <cac:Country><cbc:Name/></cac:Country>';
        $lines[] = '      </cac:PostalAddress>';
        $lines[] = '    </cac:IssuerParty>';
        $lines[] = '  </cac:AdditionalDocumentReference>';
        return $lines;
    }

    private function buildHeader(array $data): array
    {
        // BU FONKSİYON ARTIK KULLANILMIYOR, YERİNE DAHA KÜÇÜK FONKSİYONLAR GELDİ
        // GELECEKTE TEMİZLENEBİLİR
        return [];
    }

    private function buildSupplierParty(array $supplierData): array
    {
        $out = [];
        $vknTckn = (string)($supplierData['vkn_tckn'] ?? $supplierData['vkn'] ?? $supplierData['tckn'] ?? '');
        $scheme  = strlen($vknTckn) === 11 ? 'TCKN' : 'VKN';
        $name    = (string)($supplierData['name'] ?? 'Tedarikçi');
        $street  = (string)($supplierData['street'] ?? '');
        $district= (string)($supplierData['district'] ?? '');
        $city    = (string)($supplierData['city'] ?? '');
        $postal  = (string)($supplierData['postal_zone'] ?? '');
        $country = (string)($supplierData['country'] ?? 'Türkiye');
        $taxOff  = (string)($supplierData['tax_office'] ?? '');
        $phone   = (string)($supplierData['phone'] ?? '');
        $email   = (string)($supplierData['email'] ?? '');

        $out[] = '  <cac:AccountingSupplierParty>';
        $out[] = '    <cac:Party>';
        $out[] = '      <cac:PartyIdentification><cbc:ID schemeID="' . $scheme . '">' . $this->escape($vknTckn) . '</cbc:ID></cac:PartyIdentification>';
        $out[] = '      <cac:PartyName><cbc:Name>' . $this->escape($name) . '</cbc:Name></cac:PartyName>';
        $out[] = '      <cac:PostalAddress>';
        $out[] = '        <cbc:StreetName>' . $this->escape($street) . '</cbc:StreetName>';
        $out[] = '        <cbc:CitySubdivisionName>' . $this->escape($district) . '</cbc:CitySubdivisionName>';
        $out[] = '        <cbc:CityName>' . $this->escape($city) . '</cbc:CityName>';
        if ($postal !== '') { $out[] = '        <cbc:PostalZone>' . $this->escape($postal) . '</cbc:PostalZone>'; }
        $out[] = '        <cac:Country><cbc:Name>' . $this->escape($country) . '</cbc:Name></cac:Country>';
        $out[] = '      </cac:PostalAddress>';
        if ($taxOff !== '') {
            $out[] = '      <cac:PartyTaxScheme><cac:TaxScheme><cbc:Name>' . $this->escape($taxOff) . '</cbc:Name></cac:TaxScheme></cac:PartyTaxScheme>';
        }
        if ($phone !== '' || $email !== '') {
            $out[] = '      <cac:Contact>';
            if ($phone !== '') { $out[] = '        <cbc:Telephone>' . $this->escape($phone) . '</cbc:Telephone>'; }
            if ($email !== '') { $out[] = '        <cbc:ElectronicMail>' . $this->escape($email) . '</cbc:ElectronicMail>'; }
            $out[] = '      </cac:Contact>';
        }
        $out[] = '    </cac:Party>';
        $out[] = '  </cac:AccountingSupplierParty>';

        return $out;
    }

    private function buildCustomerParty(array $customerData): array
    {
        $out = [];
        $vknTckn = (string)($customerData['vkn_tckn'] ?? $customerData['vkn'] ?? $customerData['tckn'] ?? '');
        $scheme  = strlen($vknTckn) === 11 ? 'TCKN' : (strlen($vknTckn) === 10 ? 'VKN' : 'VKN');
        $name    = (string)($customerData['name'] ?? 'Müşteri');
        $street  = (string)($customerData['street'] ?? '');
        $district= (string)($customerData['district'] ?? '');
        $city    = (string)($customerData['city'] ?? '');
        $email   = (string)($customerData['email'] ?? '');
        $taxOff  = (string)($customerData['tax_office'] ?? '');
        $firstName = (string)($customerData['first_name'] ?? '');
        $familyName= (string)($customerData['family_name'] ?? '');

        $out[] = '  <cac:AccountingCustomerParty>';
        $out[] = '    <cac:Party>';
        $out[] = '      <cac:PartyIdentification><cbc:ID schemeID="' . $scheme . '">' . $this->escape($vknTckn) . '</cbc:ID></cac:PartyIdentification>';
        
        // Örnek XML'e göre, TCKN olsa bile PartyName eklenmeli.
        if ($name !== '') {
            $out[] = '      <cac:PartyName><cbc:Name>' . $this->escape($name) . '</cbc:Name></cac:PartyName>';
        }

        $out[] = '      <cac:PostalAddress>';
        $out[] = '        <cbc:StreetName>' . $this->escape($street) . '</cbc:StreetName>';
        $out[] = '        <cbc:CitySubdivisionName>' . $this->escape($district) . '</cbc:CitySubdivisionName>';
        $out[] = '        <cbc:CityName>' . $this->escape($city) . '</cbc:CityName>';
        $out[] = '        <cac:Country><cbc:Name>Türkiye</cbc:Name></cac:Country>';
        $out[] = '      </cac:PostalAddress>';
        if ($taxOff !== '') {
            $out[] = '      <cac:PartyTaxScheme><cac:TaxScheme><cbc:Name>' . $this->escape($taxOff) . '</cbc:Name></cac:TaxScheme></cac:PartyTaxScheme>';
        }
        if ($email !== '') {
            $out[] = '      <cac:Contact><cbc:ElectronicMail>' . $this->escape($email) . '</cbc:ElectronicMail></cac:Contact>';
        }

        // TCKN ise Person zorunlu
        if ($scheme === 'TCKN') {
            if ($firstName === '' && $familyName === '') { $firstName = 'AD'; $familyName = 'SOYAD'; }
            $out[] = '      <cac:Person>';
            $out[] = '        <cbc:FirstName>' . $this->escape($firstName) . '</cbc:FirstName>';
            $out[] = '        <cbc:FamilyName>' . $this->escape($familyName) . '</cbc:FamilyName>';
            $out[] = '      </cac:Person>';
        }

        $out[] = '    </cac:Party>';
        $out[] = '  </cac:AccountingCustomerParty>';

        return $out;
    }

    private function buildAdditionalDocuments(array $internet, string $issueDate): array
    {
        $out = [];
        $web = trim((string)($internet['webAddress'] ?? ''));
        $payType = trim((string)($internet['paymentType'] ?? ''));
        $platform = trim((string)($internet['platform'] ?? ''));
        $date = trim((string)($internet['paymentDate'] ?? $internet['date'] ?? ''));

        if ($web === '' && $payType === '' && $platform === '' && $date === '') {
            return $out; // Boş blok yazma
        }

        // GİB internet satış bloğu
        $out[] = '  <cac:AdditionalDocumentReference>';
        $out[] = '    <cbc:ID>INTERNET_SATIS</cbc:ID>';
        $out[] = '    <cbc:IssueDate>' . $this->escape($issueDate) . '</cbc:IssueDate>';
        $out[] = '    <cbc:DocumentTypeCode>ODEME_SEKLI</cbc:DocumentTypeCode>';
        if ($payType !== '') {
            $out[] = '    <cbc:DocumentType>' . $this->escape($payType) . '</cbc:DocumentType>';
        }
        $out[] = '    <cac:IssuerParty>';
        if ($web !== '') { $out[] = '      <cbc:WebsiteURI>' . $this->escape($web) . '</cbc:WebsiteURI>'; }
        // cbc EndpointID ve IndustryClassificationCode ekle
        $out[] = '      <cbc:EndpointID/>';
        $out[] = '      <cbc:IndustryClassificationCode/>';
        // sonra CAC grupları
        $out[] = '      <cac:PartyIdentification><cbc:ID/></cac:PartyIdentification>';
        $out[] = '      <cac:PartyName><cbc:Name>' . $this->escape($platform) . '</cbc:Name></cac:PartyName>';
        $out[] = '      <cac:PostalAddress>';
        $out[] = '        <cbc:CitySubdivisionName/>';
        $out[] = '        <cbc:CityName/>';
        $out[] = '        <cac:Country><cbc:Name/></cac:Country>';
        $out[] = '      </cac:PostalAddress>';
        $out[] = '    </cac:IssuerParty>';
        $out[] = '  </cac:AdditionalDocumentReference>';

        return $out;
    }

    private function buildTaxTotals(array $taxTotals, string $currency): array
    {
        $out = [];
        // taxTotals: [ [ 'rate' => 20, 'taxable' => 182.5, 'tax' => 36.5 ], ... ]
        $sum = 0.0;
        foreach ($taxTotals as $t) { $sum += (float)($t['tax'] ?? 0); }

        $out[] = '  <cac:TaxTotal>';
        $out[] = '    <cbc:TaxAmount currencyID="' . $this->escape($currency) . '">' . number_format($sum, 2, '.', '') . '</cbc:TaxAmount>';
        foreach ($taxTotals as $t) {
            $rate = (float)($t['rate'] ?? 0);
            $taxable = (float)($t['taxable'] ?? 0);
            $tax = (float)($t['tax'] ?? 0);
            $out[] = '    <cac:TaxSubtotal>';
            $out[] = '      <cbc:TaxableAmount currencyID="' . $this->escape($currency) . '">' . number_format($taxable, 2, '.', '') . '</cbc:TaxableAmount>';
            $out[] = '      <cbc:TaxAmount currencyID="' . $this->escape($currency) . '">' . number_format($tax, 2, '.', '') . '</cbc:TaxAmount>';
            $out[] = '      <cbc:Percent>' . $this->escape((string)$rate) . '</cbc:Percent>';
            $out[] = '      <cac:TaxCategory><cac:TaxScheme><cbc:Name>GERÇEK USULDE KATMA DEĞER VERGİSİ</cbc:Name><cbc:TaxTypeCode>0015</cbc:TaxTypeCode></cac:TaxScheme></cac:TaxCategory>';
            $out[] = '    </cac:TaxSubtotal>';
        }
        $out[] = '  </cac:TaxTotal>';
        return $out;
    }

    private function buildLegalMonetaryTotal(array $totals, string $currency): array
    {
        $out = [];
        $lineExt = (float)($totals['line_extension'] ?? 0);
        $taxEx   = (float)($totals['tax_exclusive'] ?? $lineExt);
        $taxInc  = (float)($totals['tax_inclusive'] ?? $taxEx);
        $allow   = (float)($totals['allowance_total'] ?? 0);
        $payable = (float)($totals['payable'] ?? $taxInc - $allow);

        $out[] = '  <cac:LegalMonetaryTotal>';
        $out[] = '    <cbc:LineExtensionAmount currencyID="' . $this->escape($currency) . '">' . number_format($lineExt, 2, '.', '') . '</cbc:LineExtensionAmount>';
        $out[] = '    <cbc:TaxExclusiveAmount currencyID="' . $this->escape($currency) . '">' . number_format($taxEx, 2, '.', '') . '</cbc:TaxExclusiveAmount>';
        $out[] = '    <cbc:TaxInclusiveAmount currencyID="' . $this->escape($currency) . '">' . number_format($taxInc, 2, '.', '') . '</cbc:TaxInclusiveAmount>';
        $out[] = '    <cbc:AllowanceTotalAmount currencyID="' . $this->escape($currency) . '">' . number_format($allow, 2, '.', '') . '</cbc:AllowanceTotalAmount>';
        $out[] = '    <cbc:PayableAmount currencyID="' . $this->escape($currency) . '">' . number_format($payable, 2, '.', '') . '</cbc:PayableAmount>';
        $out[] = '  </cac:LegalMonetaryTotal>';
        return $out;
    }

    private function buildInvoiceLines(array $items, string $currency): array
    {
        $out = [];
        $lineNum = 0;
        foreach ($items as $it) {
            $lineNum++;
            $name  = (string)($it['name'] ?? 'Satır');
            $qty   = (float)($it['quantity'] ?? 1);
            $unit  = (string)($it['unit'] ?? 'C62');
            $unitPrice = (float)($it['unit_price'] ?? 0);
            $vatRate   = (float)($it['vat_rate'] ?? 0);
            $lineTotal = $qty * $unitPrice;
            $vatAmount = $lineTotal * ($vatRate / 100);
            $sku = (string)($it['sku'] ?? '');

            $out[] = '  <cac:InvoiceLine>';
            $out[] = '    <cbc:ID>' . $this->escape((string)$lineNum) . '</cbc:ID>';
            $out[] = '    <cbc:InvoicedQuantity unitCode="' . $this->escape($this->mapUnit($unit)) . '">' . number_format($qty, 2, '.', '') . '</cbc:InvoicedQuantity>';
            $out[] = '    <cbc:LineExtensionAmount currencyID="' . $this->escape($currency) . '">' . number_format($lineTotal, 2, '.', '') . '</cbc:LineExtensionAmount>';
            $out[] = '    <cac:TaxTotal>';
            $out[] = '      <cbc:TaxAmount currencyID="' . $this->escape($currency) . '">' . number_format($vatAmount, 2, '.', '') . '</cbc:TaxAmount>';
            $out[] = '      <cac:TaxSubtotal>';
            $out[] = '        <cbc:TaxableAmount currencyID="' . $this->escape($currency) . '">' . number_format($lineTotal, 2, '.', '') . '</cbc:TaxableAmount>';
            $out[] = '        <cbc:TaxAmount currencyID="' . $this->escape($currency) . '">' . number_format($vatAmount, 2, '.', '') . '</cbc:TaxAmount>';
            $out[] = '        <cbc:Percent>' . $this->escape((string)$vatRate) . '</cbc:Percent>';
            $out[] = '        <cac:TaxCategory><cac:TaxScheme><cbc:Name>GERÇEK USULDE KATMA DEĞER VERGİSİ</cbc:Name><cbc:TaxTypeCode>0015</cbc:TaxTypeCode></cac:TaxScheme></cac:TaxCategory>';
            $out[] = '      </cac:TaxSubtotal>';
            $out[] = '    </cac:TaxTotal>';
            $out[] = '    <cac:Item>';
            $out[] = '      <cbc:Name>' . $this->escape($name) . '</cbc:Name>';
            if ($sku !== '') {
                $out[] = '      <cac:SellersItemIdentification><cbc:ID>' . $this->escape($sku) . '</cbc:ID></cac:SellersItemIdentification>';
            }
            $out[] = '    </cac:Item>';
            $out[] = '    <cac:Price><cbc:PriceAmount currencyID="' . $this->escape($currency) . '">' . number_format($unitPrice, 2, '.', '') . '</cbc:PriceAmount></cac:Price>';
            $out[] = '  </cac:InvoiceLine>';
        }
        return $out;
    }

    private function buildDelivery(array $delivery): array
    {
        $out = [];
        $date = (string)($delivery['actualDeliveryDate'] ?? $delivery['date'] ?? '');
        $carrierName = (string)($delivery['carrier_name'] ?? '');
        $carrierVkn  = (string)($delivery['carrier_vkn'] ?? '');
        if ($date === '' && $carrierName === '' && $carrierVkn === '') { return $out; }

        $out[] = '  <cac:Delivery>';
        if ($date !== '') { $out[] = '    <cbc:ActualDeliveryDate>' . $this->escape($date) . '</cbc:ActualDeliveryDate>'; }
        if ($carrierName !== '' || $carrierVkn !== '') {
            $out[] = '    <cac:CarrierParty>';
            // Şema: Her durumda PartyIdentification önce gelmeli (VKN yoksa boş ID)
            if ($carrierVkn !== '') {
                $out[] = '      <cac:PartyIdentification><cbc:ID schemeID="VKN">' . $this->escape($carrierVkn) . '</cbc:ID></cac:PartyIdentification>';
            } else {
                $out[] = '      <cac:PartyIdentification><cbc:ID/></cac:PartyIdentification>';
            }
            if ($carrierName !== '') { $out[] = '      <cac:PartyName><cbc:Name>' . $this->escape($carrierName) . '</cbc:Name></cac:PartyName>'; }
            $out[] = '      <cac:PostalAddress><cbc:CitySubdivisionName/><cbc:CityName/><cac:Country><cbc:Name/></cac:Country></cac:PostalAddress>';
            $out[] = '    </cac:CarrierParty>';
        }
        $out[] = '  </cac:Delivery>';
        return $out;
    }

    private function buildAllowanceCharge(string $currency): array
    {
        return [
            '  <cac:AllowanceCharge>',
            '    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>',
            '    <cbc:Amount currencyID="' . $this->escape($currency) . '">0</cbc:Amount>',
            '  </cac:AllowanceCharge>',
        ];
    }

    private function buildSignaturePlaceholder(string $uuid, array $supplierData): array
    {
        $vknTckn = (string)($supplierData['vkn_tckn'] ?? $supplierData['vkn'] ?? $supplierData['tckn'] ?? '');
        $scheme  = strlen($vknTckn) === 11 ? 'TCKN' : 'VKN';
        return [
            '  <ext:UBLExtensions>',
            '    <ext:UBLExtension>',
            '      <ext:ExtensionContent>',
            '        <ds:Signature>',
            '          <ds:SignedInfo>',
            '            <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>',
            '            <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>',
            '            <ds:Reference URI="">',
            '              <ds:Transforms>',
            '                <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>',
            '              </ds:Transforms>',
            '              <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>',
            '              <ds:DigestValue/>',
            '            </ds:Reference>',
            '          </ds:SignedInfo>',
            '          <ds:SignatureValue/>',
            '          <ds:KeyInfo>',
            '            <ds:KeyValue><ds:RSAKeyValue><ds:Modulus/><ds:Exponent/></ds:RSAKeyValue></ds:KeyValue>',
            '            <ds:X509Data><ds:X509Certificate/></ds:X509Data>',
            '          </ds:KeyInfo>',
            '          <ds:Object>',
            '            <xades:QualifyingProperties Target="#Signature_' . $this->escape($uuid) . '">',
            '              <xades:SignedProperties Id="SignedProperties_' . $this->escape($uuid) . '">',
            '                <xades:SignedSignatureProperties>',
            '                  <xades:SigningTime>' . date('c') . '</xades:SigningTime>',
            '                  <xades:SigningCertificate>',
            '                    <xades:Cert>',
            '                      <xades:CertDigest>',
            '                        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>',
            '                        <ds:DigestValue/>',
            '                      </xades:CertDigest>',
            '                      <xades:IssuerSerial>',
            '                        <ds:X509IssuerName/>',
            '                        <ds:X509SerialNumber>0</ds:X509SerialNumber>',
            '                      </xades:IssuerSerial>',
            '                    </xades:Cert>',
            '                  </xades:SigningCertificate>',
            '                </xades:SignedSignatureProperties>',
            '              </xades:SignedProperties>',
            '            </xades:QualifyingProperties>',
            '          </ds:Object>',
            '        </ds:Signature>',
            '      </ext:ExtensionContent>',
            '    </ext:UBLExtension>',
            '  </ext:UBLExtensions>',
        ];
    }

    private function buildSignatureBlock(string $uuid, array $supplierData): array
    {
        $vknTckn = (string)($supplierData['vkn_tckn'] ?? $supplierData['vkn'] ?? $supplierData['tckn'] ?? '');
        $scheme  = strlen($vknTckn) === 11 ? 'TCKN' : 'VKN';
        return [
            '  <cac:Signature>',
            '    <cbc:ID schemeID="VKN_TCKN">' . $this->escape($vknTckn) . '</cbc:ID>',
            '    <cac:SignatoryParty>',
            '      <cac:PartyIdentification><cbc:ID schemeID="' . $scheme . '">' . $this->escape($vknTckn) . '</cbc:ID></cac:PartyIdentification>',
            '      <cac:PostalAddress>',
            '        <cbc:CitySubdivisionName/>',
            '        <cbc:CityName/>',
            '        <cac:Country><cbc:Name>Türkiye</cbc:Name></cac:Country>',
            '      </cac:PostalAddress>',
            '    </cac:SignatoryParty>',
            '    <cac:DigitalSignatureAttachment><cac:ExternalReference><cbc:URI>#Signature_' . $this->escape($uuid) . '</cbc:URI></cac:ExternalReference></cac:DigitalSignatureAttachment>',
            '  </cac:Signature>',
        ];
    }

    private function mapUnit(string $unit): string
    {
        $upper = mb_strtoupper($unit);
        return match ($upper) {
            'ADET', 'C62' => 'C62',
            'KG', 'KGM' => 'KGM',
            'LT', 'LTR' => 'LTR',
            'METRE', 'M', 'MTR' => 'MTR',
            default => 'C62',
        };
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) { return $time; }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) { return $time . ':00'; }
        $ts = strtotime($time);
        return $ts ? date('H:i:s', $ts) : date('H:i:s');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}


