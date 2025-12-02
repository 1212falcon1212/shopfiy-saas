<?php

namespace App\Services;

use App\Models\Order;
use DOMDocument;
use Illuminate\Support\Str;

class UblGenerator
{
    private function format($number)
    {
        return number_format((float)$number, 2, '.', '');
    }

    public function generate(Order $order, $uuid, $documentId = null)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $invoice = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', 'http://uri.etsi.org/01903/v1.3.2#');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $dom->appendChild($invoice);

        // --- UBLEXTENSIONS (Şema gereksinimi - en başta olmalı) ---
        // ExtensionContent boş olamaz, içinde ds:Signature elementi olmalı
        // KolaySoft bu boş signature'ı gerçek dijital imza ile dolduracak
        $ublExtensions = $dom->createElement('ext:UBLExtensions');
        $invoice->appendChild($ublExtensions);
        $ublExtension = $dom->createElement('ext:UBLExtension');
        $ublExtensions->appendChild($ublExtension);
        $extensionContent = $dom->createElement('ext:ExtensionContent');
        $ublExtension->appendChild($extensionContent);
        
        // Boş ds:Signature elementi ekle (örnek kodlarda böyle)
        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');
        $signature->setAttribute('xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $signature->setAttribute('Id', 'Signature_' . $uuid);
        $extensionContent->appendChild($signature);
        
        // SignedInfo (boş değerlerle)
        $signedInfo = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:SignedInfo');
        $signature->appendChild($signedInfo);
        
        $canonMethod = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonMethod);
        
        $sigMethod = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($sigMethod);
        
        $reference = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Reference');
        $reference->setAttribute('URI', '');
        $signedInfo->appendChild($reference);
        
        $transforms = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Transforms');
        $reference->appendChild($transforms);
        
        $transform = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);
        
        $digestMethod = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);
        
        $digestValue = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue');
        $reference->appendChild($digestValue);
        
        // SignatureValue (boş)
        $sigValue = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:SignatureValue');
        $signature->appendChild($sigValue);
        
        // KeyInfo (boş)
        $keyInfo = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:KeyInfo');
        $signature->appendChild($keyInfo);
        
        $keyValue = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:KeyValue');
        $keyInfo->appendChild($keyValue);
        
        $rsaKeyValue = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:RSAKeyValue');
        $keyValue->appendChild($rsaKeyValue);
        
        $modulus = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Modulus');
        $rsaKeyValue->appendChild($modulus);
        
        $exponent = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Exponent');
        $rsaKeyValue->appendChild($exponent);
        
        $x509Data = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509Data');
        $keyInfo->appendChild($x509Data);
        
        $x509Cert = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509Certificate');
        $x509Data->appendChild($x509Cert);
        
        // Object (xades için)
        $object = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Object');
        $signature->appendChild($object);
        
        $qualifyingProps = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualifyingProps->setAttribute('xmlns:xades', 'http://uri.etsi.org/01903/v1.3.2#');
        $qualifyingProps->setAttribute('Target', '#Signature_' . $uuid);
        $object->appendChild($qualifyingProps);
        
        $signedProps = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignedProperties');
        $signedProps->setAttribute('Id', 'SignedProperties_' . $uuid);
        $qualifyingProps->appendChild($signedProps);
        
        $signedSigProps = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignedSignatureProperties');
        $signedProps->appendChild($signedSigProps);
        
        $signingTime = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigningTime');
        $signingTime->nodeValue = date('c');
        $signedSigProps->appendChild($signingTime);
        
        // SigningCertificate zorunlu (şematron gereksinimi)
        $signingCert = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigningCertificate');
        $signedSigProps->appendChild($signingCert);
        
        $cert = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:Cert');
        $signingCert->appendChild($cert);
        
        $certDigest = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:CertDigest');
        $cert->appendChild($certDigest);
        
        $digestMethod = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#sha384');
        $certDigest->appendChild($digestMethod);
        
        $digestValue = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue');
        $certDigest->appendChild($digestValue);
        
        $issuerSerial = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:IssuerSerial');
        $cert->appendChild($issuerSerial);
        
        $x509IssuerName = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509IssuerName');
        $x509IssuerName->nodeValue = ''; // Boş string kabul edilir
        $issuerSerial->appendChild($x509IssuerName);
        
        $x509SerialNumber = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509SerialNumber');
        $x509SerialNumber->nodeValue = '0'; // Integer alan için 0 placeholder değeri
        $issuerSerial->appendChild($x509SerialNumber);

        // --- BAŞLIK BİLGİLERİ ---
        $this->addCbc($dom, $invoice, 'UBLVersionID', '2.1');
        $this->addCbc($dom, $invoice, 'CustomizationID', 'TR1.2');
        $this->addCbc($dom, $invoice, 'ProfileID', 'EARSIVFATURA');
        // cbc:ID documentId ile eşleşmeli (KolaySoft kontrolü için)
        $invoiceId = $documentId ?? $order->order_number;
        $this->addCbc($dom, $invoice, 'ID', $invoiceId);
        $this->addCbc($dom, $invoice, 'CopyIndicator', 'false');
        $this->addCbc($dom, $invoice, 'UUID', $uuid);
        $this->addCbc($dom, $invoice, 'IssueDate', now()->format('Y-m-d'));
        $this->addCbc($dom, $invoice, 'IssueTime', now()->format('H:i:s'));
        // Fatura tipi: Her zaman SATIS (normal satış faturası)
        $this->addCbc($dom, $invoice, 'InvoiceTypeCode', 'SATIS');
        
        // Fatura Notu (opsiyonel ama örnekte var)
        if (isset($order->note) && $order->note) {
            $this->addCbc($dom, $invoice, 'Note', $order->note);
        }
        
        $this->addCbc($dom, $invoice, 'DocumentCurrencyCode', $order->currency ?? 'TRY');

        // --- SATIR SAYISI (LineCountNumeric) ---
        $lineItems = $order->line_items ?? [];
        $shippingLines = $order->shipping_lines ?? [];
        $totalLineCount = count($lineItems) + count($shippingLines);
        $this->addCbc($dom, $invoice, 'LineCountNumeric', $totalLineCount);

        // --- SİPARİŞ REFERANSI (OrderReference) ---
        $orderRef = $dom->createElement('cac:OrderReference');
        $invoice->appendChild($orderRef);
        $this->addCbc($dom, $orderRef, 'ID', $order->order_number);
        $this->addCbc($dom, $orderRef, 'IssueDate', now()->format('Y-m-d'));

        // --- EK DÖKÜMAN REFERANSLARI (AdditionalDocumentReference) ---
        // Gönderim Şekli
        $addDocRef1 = $dom->createElement('cac:AdditionalDocumentReference');
        $invoice->appendChild($addDocRef1);
        $this->addCbc($dom, $addDocRef1, 'ID', Str::random(13));
        $this->addCbc($dom, $addDocRef1, 'IssueDate', now()->format('Y-m-d'));
        $this->addCbc($dom, $addDocRef1, 'DocumentTypeCode', 'GONDERIM_SEKLI');
        $this->addCbc($dom, $addDocRef1, 'DocumentType', 'ELEKTRONIK');

        // Ödeme Şekli
        $addDocRef2 = $dom->createElement('cac:AdditionalDocumentReference');
        $invoice->appendChild($addDocRef2);
        $this->addCbc($dom, $addDocRef2, 'ID', 'INTERNET_SATIS');
        $this->addCbc($dom, $addDocRef2, 'IssueDate', now()->format('Y-m-d'));
        $this->addCbc($dom, $addDocRef2, 'DocumentTypeCode', 'ODEME_SEKLI');
        $this->addCbc($dom, $addDocRef2, 'DocumentType', 'ODEMEARACISI');

        // --- SIGNATURE (Şema gereksinimi - AccountingSupplierParty'den önce olmalı) ---
        $supplierVknTckn = config('services.kolaysoft.supplier_vkn_tckn', '11111111111');
        $signature = $dom->createElement('cac:Signature');
        $invoice->appendChild($signature);
        
        // Signature ID (schemeID her zaman VKN_TCKN olmalı - şematron gereksinimi)
        $sigId = $dom->createElement('cbc:ID');
        $sigId->setAttribute('schemeID', 'VKN_TCKN');
        $sigId->nodeValue = $supplierVknTckn;
        $signature->appendChild($sigId);
        
        // SignatoryParty
        $signatoryParty = $dom->createElement('cac:SignatoryParty');
        $signature->appendChild($signatoryParty);
        
        $sigPartyId = $dom->createElement('cac:PartyIdentification');
        $signatoryParty->appendChild($sigPartyId);
        $sigPartyIdElement = $dom->createElement('cbc:ID');
        $sigPartyIdElement->setAttribute('schemeID', strlen($supplierVknTckn) === 11 ? 'TCKN' : 'VKN');
        $sigPartyIdElement->nodeValue = $supplierVknTckn;
        $sigPartyId->appendChild($sigPartyIdElement);
        
        $sigPostalAddress = $dom->createElement('cac:PostalAddress');
        $signatoryParty->appendChild($sigPostalAddress);
        // UBL şemasına göre sıralama: StreetName, CitySubdivisionName, CityName, Country
        $this->addCbc($dom, $sigPostalAddress, 'StreetName', '');
        $this->addCbc($dom, $sigPostalAddress, 'CitySubdivisionName', '');
        $this->addCbc($dom, $sigPostalAddress, 'CityName', '');
        $sigCountry = $dom->createElement('cac:Country');
        $sigPostalAddress->appendChild($sigCountry);
        $this->addCbc($dom, $sigCountry, 'Name', 'Türkiye');
        
        // DigitalSignatureAttachment (UBLExtensions'daki signature'a referans)
        $digSigAttachment = $dom->createElement('cac:DigitalSignatureAttachment');
        $signature->appendChild($digSigAttachment);
        $extRef = $dom->createElement('cac:ExternalReference');
        $digSigAttachment->appendChild($extRef);
        $this->addCbc($dom, $extRef, 'URI', '#Signature_' . $uuid);

        // --- GÖNDERİCİ (SİZ) ---
        // Supplier VKN/TCKN config'den alınmalı (KolaySoft kullanıcı bilgileriyle eşleşmeli)
        $supplierName = config('services.kolaysoft.supplier_name', 'SaaS Magaza A.S.');
        
        $supplier = $dom->createElement('cac:AccountingSupplierParty');
        $invoice->appendChild($supplier);
        $party = $dom->createElement('cac:Party');
        $supplier->appendChild($party);
        $this->addPartyIdentification($dom, $party, $supplierVknTckn);
        $partyName = $dom->createElement('cac:PartyName');
        $party->appendChild($partyName);
        $this->addCbc($dom, $partyName, 'Name', $supplierName);
        
        // Gönderici Adresi (örnek XML'de var - sıralama önemli!)
        $supplierPostalAddress = $dom->createElement('cac:PostalAddress');
        $party->appendChild($supplierPostalAddress);
        // UBL şemasına göre sıralama: StreetName, CitySubdivisionName, CityName, Country
        $this->addCbc($dom, $supplierPostalAddress, 'StreetName', '');
        $this->addCbc($dom, $supplierPostalAddress, 'CitySubdivisionName', '');
        $this->addCbc($dom, $supplierPostalAddress, 'CityName', '');
        $supplierCountry = $dom->createElement('cac:Country');
        $supplierPostalAddress->appendChild($supplierCountry);
        $this->addCbc($dom, $supplierCountry, 'Name', 'Türkiye');
        
        // Gönderici Vergi Dairesi (örnek XML'de var)
        $partyTaxScheme = $dom->createElement('cac:PartyTaxScheme');
        $party->appendChild($partyTaxScheme);
        $taxScheme = $dom->createElement('cac:TaxScheme');
        $partyTaxScheme->appendChild($taxScheme);
        $this->addCbc($dom, $taxScheme, 'Name', ''); // Boş bırakıyoruz, env'den alınabilir
        
        // --- ALICI (MÜŞTERİ) ---
        $customer = $dom->createElement('cac:AccountingCustomerParty');
        $invoice->appendChild($customer);
        $custParty = $dom->createElement('cac:Party');
        $customer->appendChild($custParty);
        
        // Müşteri TCKN/VKN
        $customerId = $order->shipping_address['customer_id'] ?? '11111111111';
        $this->addPartyIdentification($dom, $custParty, $customerId);
        
        // Müşteri Adı
        $custPartyName = $dom->createElement('cac:PartyName');
        $custParty->appendChild($custPartyName);
        $this->addCbc($dom, $custPartyName, 'Name', $order->customer_name ?? 'Musteri');
        
        // Müşteri Adresi (PostalAddress) - UBL şemasına göre sıralama önemli!
        // PostalAddress her zaman eklenmeli (şema gereksinimi)
        $postalAddress = $dom->createElement('cac:PostalAddress');
        $custParty->appendChild($postalAddress);
        
        // Sıralama: StreetName, CitySubdivisionName, CityName, Country
        $streetName = '';
        if (isset($order->shipping_address)) {
            $streetName = $order->shipping_address['address1'] ?? $order->shipping_address['street'] ?? '';
        }
        $this->addCbc($dom, $postalAddress, 'StreetName', $streetName);
        
        // CitySubdivisionName ve CityName zorunlu (şema gereksinimi - Country'den önce gelmeli)
        $city = '';
        if (isset($order->shipping_address['city'])) {
            $city = $order->shipping_address['city'];
        }
        $this->addCbc($dom, $postalAddress, 'CitySubdivisionName', $city);
        $this->addCbc($dom, $postalAddress, 'CityName', $city);
        
        $country = $dom->createElement('cac:Country');
        $postalAddress->appendChild($country);
        $countryName = 'Türkiye';
        if (isset($order->shipping_address['country'])) {
            $countryName = $order->shipping_address['country'];
        }
        $this->addCbc($dom, $country, 'Name', $countryName);
        
        // Müşteri İletişim Bilgileri
        if (isset($order->customer_email)) {
            $contact = $dom->createElement('cac:Contact');
            $custParty->appendChild($contact);
            $this->addCbc($dom, $contact, 'ElectronicMail', $order->customer_email);
        }
        
        // TCKN kullanıldığında Person elemanı zorunlu (şematron gereksinimi)
        $customerId = $order->shipping_address['customer_id'] ?? '11111111111';
        if (strlen($customerId) === 11) { // TCKN
            $person = $dom->createElement('cac:Person');
            $custParty->appendChild($person);
            // Müşteri adından isim ve soyisim çıkar
            $customerName = $order->customer_name ?? 'Ali Veli';
            $nameParts = explode(' ', $customerName, 2);
            $this->addCbc($dom, $person, 'FirstName', $nameParts[0] ?? 'Ali');
            if (isset($nameParts[1])) {
                $this->addCbc($dom, $person, 'FamilyName', $nameParts[1]);
            } else {
                $this->addCbc($dom, $person, 'FamilyName', 'Veli');
            }
        }

        // --- KALEMLER (ÜRÜNLER + KARGO) - Önce toplamları hesapla ---
        
        $lineItems = $order->line_items ?? [];
        $shippingLines = $order->shipping_lines ?? [];
        
        $taxTotals = []; 
        $invoiceLineExtensionAmount = 0; // Mal Hizmet Toplamı
        $invoiceTaxExclusiveAmount = 0;  // Matrah
        $invoiceTaxInclusiveAmount = 0;  // Vergili Toplam

        $index = 1;
        $lineItemsToAdd = []; // InvoiceLine'ları sonra eklemek için sakla

        // 1. Ürünleri işle (toplamları hesapla ama henüz DOM'a ekleme)
        foreach ($lineItems as $item) {
            $lineItemsToAdd[] = ['type' => 'product', 'item' => $item, 'index' => $index++];
            $this->calculateLineItemTotals($item, $order->currency, $taxTotals, $invoiceLineExtensionAmount, $invoiceTaxExclusiveAmount, $invoiceTaxInclusiveAmount);
        }

        // 2. Kargoyu işle (toplamları hesapla ama henüz DOM'a ekleme)
        foreach ($shippingLines as $ship) {
            $shippingItem = [
                'title' => $ship['title'] ?? 'Kargo Bedeli',
                'quantity' => 1,
                'price' => $ship['price'] ?? 0,
                'tax_lines' => $ship['tax_lines'] ?? []
            ];
            $lineItemsToAdd[] = ['type' => 'shipping', 'item' => $shippingItem, 'index' => $index++];
            $this->calculateLineItemTotals($shippingItem, $order->currency, $taxTotals, $invoiceLineExtensionAmount, $invoiceTaxExclusiveAmount, $invoiceTaxInclusiveAmount);
        }

        // --- TESLİMAT BİLGİLERİ (Delivery) ---
        // Örnek XML'de var, her zaman ekliyoruz (zorunlu olabilir)
        $delivery = $dom->createElement('cac:Delivery');
        $invoice->appendChild($delivery);
        $this->addCbc($dom, $delivery, 'ActualDeliveryDate', now()->format('Y-m-d'));
        
        // Kargo firması bilgisi varsa ekle
        if (isset($order->shipping_lines[0]['title'])) {
            $carrierParty = $dom->createElement('cac:CarrierParty');
            $delivery->appendChild($carrierParty);
            $carrierName = $dom->createElement('cac:PartyName');
            $carrierParty->appendChild($carrierName);
            $this->addCbc($dom, $carrierName, 'Name', $order->shipping_lines[0]['title']);
        }
        
        // --- İNDİRİM/EK ÜCRET (AllowanceCharge) ---
        // Örnek XML'de var (0 değeriyle), ekliyoruz
        $allowanceCharge = $dom->createElement('cac:AllowanceCharge');
        $invoice->appendChild($allowanceCharge);
        $this->addCbc($dom, $allowanceCharge, 'ChargeIndicator', 'false');
        $this->addCbc($dom, $allowanceCharge, 'Amount', '0.00', ['currencyID' => $order->currency ?? 'TRY']);
        
        // --- VERGİ TOPLAMLARI ---
        $taxTotal = $dom->createElement('cac:TaxTotal');
        $invoice->appendChild($taxTotal);
        
        $totalTaxAmount = 0;
        foreach ($taxTotals as $t) { $totalTaxAmount += $t['amount']; }
        $this->addCbc($dom, $taxTotal, 'TaxAmount', $this->format($totalTaxAmount), ['currencyID' => $order->currency]);

        // TaxSubtotal zorunlu (şema gereksinimi) - en az bir tane olmalı
        if (empty($taxTotals)) {
            // Vergi yoksa bile bir TaxSubtotal ekle (0 vergi ile)
            // Normal satış faturası için TaxExemptionReason eklemiyoruz
            $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
            $taxTotal->appendChild($taxSubtotal);
            $this->addCbc($dom, $taxSubtotal, 'TaxableAmount', $this->format($invoiceTaxExclusiveAmount), ['currencyID' => $order->currency]);
            $this->addCbc($dom, $taxSubtotal, 'TaxAmount', '0.00', ['currencyID' => $order->currency]);
            // Percent TaxSubtotal içinde, TaxCategory'den önce gelmeli
            $this->addCbc($dom, $taxSubtotal, 'Percent', '0');
            $taxCategory = $dom->createElement('cac:TaxCategory');
            $taxSubtotal->appendChild($taxCategory);
            // TaxScheme TaxCategory içinde (TaxExemptionReason eklemiyoruz - normal satış faturası)
            $taxScheme = $dom->createElement('cac:TaxScheme');
            $taxCategory->appendChild($taxScheme);
            $this->addCbc($dom, $taxScheme, 'Name', 'GERÇEK USULDE KATMA DEĞER VERGİSİ');
            $this->addCbc($dom, $taxScheme, 'TaxTypeCode', '0015');
        } else {
            foreach ($taxTotals as $rate => $values) {
                $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
                $taxTotal->appendChild($taxSubtotal);
                $this->addCbc($dom, $taxSubtotal, 'TaxableAmount', $this->format($values['base']), ['currencyID' => $order->currency]);
                $this->addCbc($dom, $taxSubtotal, 'TaxAmount', $this->format($values['amount']), ['currencyID' => $order->currency]);
                // Percent TaxSubtotal içinde, TaxCategory'den önce gelmeli
                $this->addCbc($dom, $taxSubtotal, 'Percent', $rate);
                $taxCategory = $dom->createElement('cac:TaxCategory');
                $taxSubtotal->appendChild($taxCategory);
                // TaxScheme TaxCategory içinde
                $taxScheme = $dom->createElement('cac:TaxScheme');
                $taxCategory->appendChild($taxScheme);
                $this->addCbc($dom, $taxScheme, 'Name', 'GERÇEK USULDE KATMA DEĞER VERGİSİ');
                $this->addCbc($dom, $taxScheme, 'TaxTypeCode', '0015');
            }
        }

        // --- GENEL TOPLAMLAR ---
        $legalTotal = $dom->createElement('cac:LegalMonetaryTotal');
        $invoice->appendChild($legalTotal);
        
        // NOT: Burası kritik. Shopify'ın total_price'ı esastır.
        // Eğer bizim hesapladığımız ($invoiceTaxInclusiveAmount) ile Shopify ($order->total_price) arasında kuruş farkı varsa
        // PayableAmount'a Shopify tutarını yazarız. (Muhasebesel yuvarlama farkı kabul edilir)
        
        $shopifyTotal = (float)$order->total_price;
        
        $this->addCbc($dom, $legalTotal, 'LineExtensionAmount', $this->format($invoiceLineExtensionAmount), ['currencyID' => $order->currency]);
        $this->addCbc($dom, $legalTotal, 'TaxExclusiveAmount', $this->format($invoiceTaxExclusiveAmount), ['currencyID' => $order->currency]);
        $this->addCbc($dom, $legalTotal, 'TaxInclusiveAmount', $this->format($invoiceTaxInclusiveAmount), ['currencyID' => $order->currency]);
        $this->addCbc($dom, $legalTotal, 'AllowanceTotalAmount', '0.00', ['currencyID' => $order->currency]);
        
        // Ödenecek Tutar: TaxInclusiveAmount ile aynı olmalı (vergili toplam)
        // Shopify'ın total_price'ı vergi dahil ise onu kullan, değilse hesaplanan vergili toplamı kullan
        $shopifyTotal = (float)$order->total_price;
        $payableAmount = ($shopifyTotal >= $invoiceTaxInclusiveAmount) ? $shopifyTotal : $invoiceTaxInclusiveAmount;
        $this->addCbc($dom, $legalTotal, 'PayableAmount', $this->format($payableAmount), ['currencyID' => $order->currency]);

        // --- INVOICE LINE'LAR (En sona eklenmeli - şema gereksinimi) ---
        foreach ($lineItemsToAdd as $lineData) {
            $this->processLineItem($dom, $invoice, $lineData['index'], $lineData['item'], $order->currency, $taxTotals, $invoiceLineExtensionAmount, $invoiceTaxExclusiveAmount, $invoiceTaxInclusiveAmount);
        }

        return $dom->saveXML();
    }
    
    // Line item toplamlarını hesapla (DOM'a eklemeden)
    private function calculateLineItemTotals($item, $currency, &$taxTotals, &$totalLine, &$totalExcl, &$totalIncl)
    {
        $qty = (float)($item['quantity'] ?? 1);
        $unitPrice = (float)($item['price'] ?? 0);
        $lineTotal = $qty * $unitPrice;
        
        // Vergi hesaplama
        $taxRate = 0;
        if (isset($item['tax_lines']) && is_array($item['tax_lines']) && !empty($item['tax_lines'])) {
            $taxLine = $item['tax_lines'][0];
            $taxRate = (float)($taxLine['rate'] ?? 0) * 100; // 0.20 -> 20
        }
        
        $taxAmount = $lineTotal * ($taxRate / 100);
        $lineNet = $lineTotal - $taxAmount;
        
        $totalLine += $lineNet;
        $totalExcl += $lineNet;
        $totalIncl += $lineTotal;
        
        if ($taxRate > 0) {
            $rateKey = (string)$taxRate;
            if (!isset($taxTotals[$rateKey])) {
                $taxTotals[$rateKey] = ['base' => 0, 'amount' => 0];
            }
            $taxTotals[$rateKey]['base'] += $lineNet;
            $taxTotals[$rateKey]['amount'] += $taxAmount;
        }
    }

    // Satır İşleme Fonksiyonu (DOM'a ekleme - toplamlar zaten hesaplanmış)
    private function processLineItem($dom, $parent, $id, $item, $currency, &$taxTotals, &$totalLine, &$totalExcl, &$totalIncl)
    {
        $qty = (float)($item['quantity'] ?? 1);
        $unitPrice = (float)($item['price'] ?? 0);
        
        // Vergi Oranı
        $taxRate = 20; 
        if (isset($item['tax_lines']) && is_array($item['tax_lines']) && !empty($item['tax_lines'])) {
            $taxRate = (float)($item['tax_lines'][0]['rate'] ?? 0) * 100;
        }

        $lineTotal = $unitPrice * $qty; 
        $taxAmount = $lineTotal * ($taxRate / 100);
        $lineNet = $lineTotal - $taxAmount;
        
        // NOT: Toplamlar zaten calculateLineItemTotals ile hesaplanmış, burada sadece DOM'a ekliyoruz

        // XML Elemanları
        $line = $dom->createElement('cac:InvoiceLine');
        $parent->appendChild($line);
        $this->addCbc($dom, $line, 'ID', $id);
        $this->addCbc($dom, $line, 'InvoicedQuantity', $qty, ['unitCode' => 'C62']);
        $this->addCbc($dom, $line, 'LineExtensionAmount', $this->format($lineTotal), ['currencyID' => $currency]);

        $taxTotalTag = $dom->createElement('cac:TaxTotal');
        $line->appendChild($taxTotalTag);
        $this->addCbc($dom, $taxTotalTag, 'TaxAmount', $this->format($taxAmount), ['currencyID' => $currency]);
        
        $taxSub = $dom->createElement('cac:TaxSubtotal');
        $taxTotalTag->appendChild($taxSub);
        $this->addCbc($dom, $taxSub, 'TaxableAmount', $this->format($lineTotal), ['currencyID' => $currency]);
        $this->addCbc($dom, $taxSub, 'TaxAmount', $this->format($taxAmount), ['currencyID' => $currency]);
        // Percent TaxSubtotal içinde, TaxCategory'den önce gelmeli
        $this->addCbc($dom, $taxSub, 'Percent', $taxRate);
        
        $taxCat = $dom->createElement('cac:TaxCategory');
        $taxSub->appendChild($taxCat);
        // TaxScheme TaxCategory içinde
        $taxSch = $dom->createElement('cac:TaxScheme');
        $taxCat->appendChild($taxSch);
        $this->addCbc($dom, $taxSch, 'Name', 'GERÇEK USULDE KATMA DEĞER VERGİSİ');
        $this->addCbc($dom, $taxSch, 'TaxTypeCode', '0015');

        $itemTag = $dom->createElement('cac:Item');
        $line->appendChild($itemTag);
        $this->addCbc($dom, $itemTag, 'Name', $item['title'] ?? 'Urun/Hizmet');

        $priceTag = $dom->createElement('cac:Price');
        $line->appendChild($priceTag);
        $this->addCbc($dom, $priceTag, 'PriceAmount', $this->format($unitPrice), ['currencyID' => $currency]);
    }

    private function addCbc($dom, $parent, $name, $value, $attributes = [])
    {
        $el = $dom->createElement('cbc:' . $name, $value);
        foreach ($attributes as $k => $v) {
            $el->setAttribute($k, $v);
        }
        $parent->appendChild($el);
    }

    private function addPartyIdentification($dom, $party, $vkn)
    {
        $pi = $dom->createElement('cac:PartyIdentification');
        $party->appendChild($pi);
        $schemeID = strlen($vkn) == 10 ? 'VKN' : 'TCKN';
        $this->addCbc($dom, $pi, 'ID', $vkn, ['schemeID' => $schemeID]);
    }
}