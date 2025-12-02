<?php

namespace App\Services;

use App\Models\InvoiceIntegration;
use App\Models\TrendyolOrder;
use App\Support\Invoice\Ubl\UblBuilder;
use App\Support\Integration\Invoice\EfaturaAi\EfaturaAi;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class EArchiveInvoiceService
{
    protected EfaturaAi $provider;
    protected UblBuilder $ublBuilder;

    public function __construct(EfaturaAi $provider, UblBuilder $ublBuilder)
    {
        $this->provider = $provider;
        $this->ublBuilder = $ublBuilder;
    }

    /**
     * Müşteri entegrasyonundaki seri etiketi ayarından bir sonraki DocumentId'yi üretir.
     * - credentials.document_series_prefix (string)
     * - credentials.document_series_next (int)
     * - credentials.document_series_padding (int)
     * Not: Etiket girilmemişse fatura kesmeyi engeller.
     */
    private function nextDocumentIdOrFail(InvoiceIntegration $integration): string
    {
        return DB::transaction(function() use ($integration) {
            /** @var InvoiceIntegration $row */
            $row = InvoiceIntegration::where('id', $integration->id)->lockForUpdate()->first();
            $creds = (array)($row->credentials ?? []);
            $prefix = (string)($creds['document_series_prefix'] ?? '');
            // Yıl yer tutucuları: {YYYY}, {YY}
            if ($prefix !== '') {
                $yearFull = date('Y');
                $yearShort = date('y');
                $prefix = str_replace(['{YYYY}', '{YY}'], [$yearFull, $yearShort], $prefix);
            }
            $next   = $creds['document_series_next'] ?? null;
            // Numeric kuyruk en az 9 hane olmalı (entegratör formatı şartı)
            $pad    = (int)($creds['document_series_padding'] ?? 9);
            if ($pad < 9) { $pad = 9; }
            if (trim($prefix) === '' || $next === null || !is_numeric($next)) {
                throw new Exception('Fatura seri etiketi tanımlı değil. Lütfen Efatura.ai ayarlarından bir başlangıç etiketi girin.');
            }
            // Format doğrulaması: 3 harf + 4 yıl + 9+ hane sayı
            if (!preg_match('/^[A-Z]{3}\d{4}$/', $prefix)) {
                throw new Exception('Seri etiketi "AAA{YYYY}" formatında olmalı (3 harf + 4 yıl). Ör: ZNP{YYYY}');
            }
            $docId = $prefix . str_pad((string) ((int)$next), $pad, '0', STR_PAD_LEFT);
            $creds['document_series_next'] = (int)$next + 1;
            $row->credentials = $creds;
            $row->save();
            return $docId;
        });
    }

    private function resolveItemName(string $marketplace, int $customerId, $item): string
    {
        $marketplace = strtolower($marketplace);
        $sku = '';
        if (is_object($item)) {
            foreach (['sku','merchant_sku','barcode'] as $prop) {
                if (isset($item->{$prop}) && is_string($item->{$prop}) && $item->{$prop} !== '') { $sku = (string)$item->{$prop}; break; }
            }
        } elseif (is_array($item)) {
            foreach (['sku','merchant_sku','barcode'] as $prop) {
                if (!empty($item[$prop]) && is_string($item[$prop])) { $sku = (string)$item[$prop]; break; }
            }
        }

        if ($sku !== '') {
            try {
                $row = \App\Models\InvoiceItemNameOverride::where('customer_id', $customerId)
                    ->where('marketplace', $marketplace)
                    ->where('sku', $sku)
                    ->where('is_active', true)
                    ->first();
                if ($row) { return (string)$row->custom_name; }
            } catch (\Throwable $__) {}
        }

        // Opsiyonel: config dosyası desteği
        $overrides = config('invoice_item_names.overrides', []);
        if ($sku !== '' && isset($overrides[$customerId][$marketplace][$sku])) {
            return (string) $overrides[$customerId][$marketplace][$sku];
        }
        // 'all' marketplace fallback
        if ($sku !== '') {
            try {
                $rowAll = \App\Models\InvoiceItemNameOverride::where('customer_id', $customerId)
                    ->where('marketplace', 'all')
                    ->where('sku', $sku)
                    ->where('is_active', true)
                    ->first();
                if ($rowAll) { return (string)$rowAll->custom_name; }
            } catch (\Throwable $__) {}
        }

        // Varsayılan ad adayları
        $candidates = [];
        if ($marketplace === 'ty') { $candidates = ['product_name','name','title']; }
        elseif ($marketplace === 'hb') { $candidates = ['name','product_name','title']; }
        elseif ($marketplace === 'n11') { $candidates = ['product_name','name','title']; }
        else { $candidates = ['name','product_name','title']; }

        if (is_object($item)) {
            foreach ($candidates as $p) { if (isset($item->{$p}) && is_string($item->{$p}) && $item->{$p} !== '') { return (string)$item->{$p}; } }
        } elseif (is_array($item)) {
            foreach ($candidates as $p) { if (!empty($item[$p])) { return (string)$item[$p]; } }
        }
        return 'Ürün';
    }

    private function normalizeCarrier(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // noktalama ve ekstra boşlukları temizle
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    private function resolveCarrierVkn(?string $carrierName): string
    {
        if (!$carrierName) { return ''; }
        $normalized = $this->normalizeCarrier($carrierName);
        $map = config('shipping_carriers', []);
        foreach ($map as $key => $vkn) {
            $kNorm = $this->normalizeCarrier((string)$key);
            if ($kNorm === $normalized || str_contains($normalized, $kNorm) || str_contains($kNorm, $normalized)) {
                return (string)$vkn;
            }
        }
        return '';
    }

    private function resolveStreetFromAddress($address): string
    {
        if (is_string($address)) { return trim($address); }
        if (!is_array($address)) { return ''; }

        $candidates = [
            'fullAddress','full_address','address','street','streetName','address1','line1'
        ];
        foreach ($candidates as $k) {
            if (!empty($address[$k]) && is_string($address[$k])) {
                return trim($address[$k]);
            }
        }

        // Bileşenlerden birleştir
        $parts = [];
        foreach (['neighborhood','quarter','mahalle','streetName','cadde','sokak','avenue','no','doorNumber','houseNumber','apartment','building','block','floor'] as $k) {
            if (!empty($address[$k]) && is_string($address[$k])) { $parts[] = trim($address[$k]); }
        }
        return trim(implode(' ', $parts));
    }

    private function resolvePostalFromAddress($address): string
    {
        if (is_array($address)) {
            foreach (['postalCode','postalcode','postCode','zipcode','zip'] as $k) {
                if (!empty($address[$k])) { return (string) $address[$k]; }
            }
        }
        return '';
    }

    /**
     * Debug modda UBL derleme sürecini detaylı logla ve dosyaya yaz.
     */
    private function logUblBuildAll($order, string $platform, array $invoiceData, string $xml): void
    {
        try {
            if (!config('efatura_ai.debug_files')) { return; }
            $platform = strtoupper($platform);
            $orderId = (int) ($order->id ?? 0);
            $baseName = "ubl-{$platform}-order-{$orderId}-" . date('Ymd-His');
            $jsonPath = storage_path("logs/{$baseName}.json");
            $xmlPath  = storage_path("logs/{$baseName}.xml");
            $orderPath  = storage_path("logs/{$baseName}-order.json");

            // JSON (invoiceData)
            @file_put_contents($jsonPath, json_encode($invoiceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            // XML (tam çıktı)
            @file_put_contents($xmlPath, $xml);
            // Order Snapshot (müşteri ve adres bilgileri dahil)
            $orderSnapshot = [
                'id' => $order->id,
                'marketplace' => $platform,
                'order_number' => $order->order_number ?? $order->order_id ?? null,
                'status' => $order->status ?? null,
                'order_date' => (string) ($order->order_date ?? ''),
                'currency' => $order->currency_code ?? $order->currency ?? null,
                'customer' => [
                    'id' => optional($order->customer)->id,
                    'name' => optional($order->customer)->name,
                    'tax_number' => optional($order->customer)->getMeta('tax_number', false),
                    'tax_office' => optional($order->customer)->getMeta('tax_office', false),
                    'company_city' => optional($order->customer)->getMeta('company_city', false),
                    'company_district' => optional($order->customer)->getMeta('company_district', false),
                    'company_address' => optional($order->customer)->getMeta('company_address', false),
                ],
                'addresses' => [
                    'billing' => $order->billing_address ?? $order->invoice_address ?? null,
                    'shipping' => $order->shipping_address ?? null,
                ],
                'cargo' => [
                    'provider' => $order->cargo_provider_name ?? null,
                    'tracking' => $order->cargo_tracking_number ?? null,
                ],
                'raw_data' => $order->raw_data ?? null,
                'items' => method_exists($order, 'items') && $order->relationLoaded('items') ? $order->items->map(function($it){
                    return [
                        'name' => $it->name ?? $it->product_name ?? $it->title ?? null,
                        'sku' => $it->sku ?? $it->merchant_sku ?? $it->barcode ?? null,
                        'quantity' => $it->quantity ?? null,
                        'price' => $it->price ?? $it->amount ?? $it->total_price ?? null,
                        'vat_rate' => $it->vat_rate ?? null,
                        'raw_data' => $it->raw_data ?? null,
                    ];
                })->toArray() : null,
            ];
            @file_put_contents($orderPath, json_encode($orderSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            Log::info('[EArchiveInvoiceService][UBL][Saved]', [
                'order_id' => $orderId,
                'platform' => $platform,
                'invoice_data_file' => $jsonPath,
                'xml_file' => $xmlPath,
                'order_snapshot_file' => $orderPath,
                'xml_length' => strlen($xml),
                'lines_count' => is_array($invoiceData['lines'] ?? null) ? count($invoiceData['lines']) : 0,
            ]);
        } catch (\Throwable $__) {
            // Sessiz yut, debug amaçlı
        }
    }

    public function sendFromTrendyolOrder(int $orderId): array
    {
        try {
            $order = TrendyolOrder::with('items', 'customer.invoiceIntegrations')->findOrFail($orderId);

            $customer = $order->customer;

            // Müşteri için aktif Efatura.ai entegrasyonunu bul
            $integration = $customer->invoiceIntegrations()
                ->where('provider', 'efatura_ai')
                ->first();

            if (!$integration) {
                throw new Exception('Müşteri için aktif Efatura.ai entegrasyonu bulunamadı.');
            }

            // Supplier meta (tedarikçi/bizim firma bilgileri)
            $supplier = [
                'name'        => (string)($customer->getMeta('company_name', false) ?: $customer->name),
                'vkn_tckn'    => (string)($customer->getMeta('tax_number', false) ?: ''),
                'tax_office'  => (string)($customer->getMeta('tax_office', false) ?: ''),
                'street'      => (string)($customer->getMeta('company_address', false) ?: ''),
                'district'    => (string)($customer->getMeta('company_district', false) ?: ''),
                'city'        => (string)($customer->getMeta('company_city', false) ?: ''),
                'postal_zone' => (string)($customer->getMeta('postal_code', false) ?: ''),
                'country'     => 'Türkiye',
                'phone'       => '',
                'email'       => '',
            ];
            // Gönderici (biz) VKN/TCKN boş ise, entegratör tarafında "Gönderici VKN/TCKN Hatası" alınır; güvenli dur.
            if (trim((string)$supplier['vkn_tckn']) === '') {
                throw new Exception('Gönderici VKN/TCKN (customer meta tax_number) eksik. Lütfen firma vergi numarasını ayarlayın.');
            }

            // Customer (buyer) from order address
            $invAddr = (array)($order->invoice_address ?? []);
            $rawData = (array)($order->raw_data ?? []);
            $buyerNode = (array)($rawData['details']['buyer'] ?? []);
            // Trendyol kuralı:
            // - is_einvoice_user == true ise: önce VKN (tax_number, 10 hane) dene; yoksa TCKN (identity veya fatura adresindeki tcIdentityNumber, 11 hane) dene.
            // - is_einvoice_user == false ise: e-Arşiv, alıcı kimlik '11111111111'.
            $buyerEinvoice = (bool)($buyerNode['is_einvoice_user'] ?? false);
            $rawVkn = (string)($buyerNode['tax_number'] ?? ($invAddr['vkn'] ?? ''));
            $rawIdentity = (string)($buyerNode['identity'] ?? '');
            $rawTckn = (string)($invAddr['tcIdentityNumber'] ?? '');

            $buyerVknTckn = '';
            $isEFatura = false;
            if ($buyerEinvoice) {
                if ($rawVkn !== '' && preg_match('/^\d{10}$/', $rawVkn) === 1) {
                    $buyerVknTckn = $rawVkn;          // Kurumsal VKN
                    $isEFatura = true;
                } elseif ($rawIdentity !== '' && preg_match('/^\d{11}$/', $rawIdentity) === 1) {
                    $buyerVknTckn = $rawIdentity;     // Bireysel e-Fatura TCKN
                    $isEFatura = true;
                } elseif ($rawTckn !== '' && preg_match('/^\d{11}$/', $rawTckn) === 1) {
                    $buyerVknTckn = $rawTckn;
                    $isEFatura = true;
                }
            }

            if ($buyerVknTckn === '') {
                // e-Arşiv: default kimlik
                $buyerVknTckn = '11111111111';
                $isEFatura = false;
            }
            $buyerFirst = (string)($order->customer_first_name ?? '');
            $buyerLast  = (string)($order->customer_last_name ?? '');
            $buyerName  = trim($buyerFirst . ' ' . $buyerLast);
            $streetT    = $this->resolveStreetFromAddress($invAddr);
            $postalT    = $this->resolvePostalFromAddress($invAddr);
            $buyer = [
                'vkn_tckn'   => $buyerVknTckn,
                'name'       => $buyerName,
                'first_name' => $buyerFirst,
                'family_name'=> $buyerLast,
                'street'     => $streetT,
                'district'   => (string)($invAddr['district'] ?? ''),
                'city'       => (string)($invAddr['city'] ?? ''),
                'postal_zone'=> $postalT,
                'email'      => (string)($order->customer_email ?? ''),
                'tax_office' => '',
            ];

            // Lines + totals
            $lines = [];
            $taxGroup = [];
            $lineExt = 0.0; $taxTotal = 0.0; // lineExt: net toplam, taxTotal: KDV toplam
            foreach ($order->items as $it) {
                $qty  = (float)($it->quantity ?? 1);
                $unit = 'C62';
                // Trendyol: price birim brüt, amount satır toplam brüt olabilir.
                // Birim fiyatı baz al; price yoksa amount'ı kullan.
                $priceGross = (float)($it->price ?? $it->amount ?? 0);
                $rate = (float)($it->vat_rate ?? 0);
                $mult = 1.0 + ($rate / 100.0);
                $lineGross = $qty * $priceGross;
                $lineNet   = $mult > 0 ? ($lineGross / $mult) : $lineGross;
                $vat       = $lineGross - $lineNet;
                $unitNet   = $mult > 0 ? ($priceGross / $mult) : $priceGross;

                $lineExt += $lineNet; $taxTotal += $vat;
                $lines[] = [
                    'name'       => (string)($this->resolveItemName('ty', (int)$customer->id, $it)),
                    'quantity'   => $qty,
                    'unit'       => $unit,
                    'unit_price' => $unitNet, // UBL'de PriceAmount net birim fiyat olmalı
                    'vat_rate'   => $rate,
                    'sku'        => (string)($it->merchant_sku ?? $it->barcode ?? ''),
                ];
                $key = (string)$rate;
                if (!isset($taxGroup[$key])) { $taxGroup[$key] = ['rate'=>$rate,'taxable'=>0.0,'tax'=>0.0]; }
                $taxGroup[$key]['taxable'] += $lineNet;
                $taxGroup[$key]['tax']     += $vat;
            }
            $taxTotals = array_values($taxGroup);
            $taxExclusive = $lineExt;            // net
            $taxInclusive = $lineExt + $taxTotal; // brüt
            $totals = [
                'line_extension' => $lineExt,
                'tax_exclusive'  => $taxExclusive,
                'tax_inclusive'  => $taxInclusive,
                'allowance_total'=> 0,
                'payable'        => $taxInclusive,
            ];

            $currency = (string)($order->currency_code ?? 'TRY');
            $issueDate= $order->order_date ? $order->order_date->format('Y-m-d') : date('Y-m-d');
            $uuid     = (string) Str::uuid();
            // Senaryo belirleme: VKN varsa e-Fatura (TEMELFATURA), yoksa e-Arşiv
            $profileId = $isEFatura ? 'TEMELFATURA' : 'EARSIVFATURA';
            
            // DocumentId: Kullanıcının tanımladığı seri etiketi ve sayaçtan gelmelidir.
            // Etiket tanımlı değilse fatura kesilmez (exception fırlatılır).
            $documentId = $this->nextDocumentIdOrFail($integration);
            
            // Kargo/taşıyıcı bilgileri: Trendyol siparişinden doldur
            $carrierName = trim((string)($order->cargo_provider_name ?? ''));
            // Önce siparişten geliyorsa kullan; yoksa config eşlemesi
            $carrierVknDb  = trim((string)($order->cargo_provider_vkn ?? $order->cargo_vkn ?? $order->cargo_tax_number ?? ''));
            $carrierVknMap = $carrierName !== '' ? (string)(config('shipping_carriers.' . strtolower($carrierName)) ?? '') : '';
            $carrierVkn    = $carrierVknDb !== '' ? $carrierVknDb : $carrierVknMap;
            // VKN bulunamazsa boş bırak; UBL tarafında boş PartyIdentification yazılacak

            $invoiceData = [
                'uuid'        => $uuid,
                'id'          => $documentId,
                'profile_id'  => $profileId,
                'type_code'   => 'SATIS',
                'issue_date'  => $issueDate,
                'issue_time'  => date('H:i:s'),
                'currency'    => $currency,
                'notes'       => ['İrsaliye yerine geçer.'],
                'supplier'    => $supplier,
                'customer'    => $buyer,
                'lines'       => $lines,
                'totals'      => $totals,
                'tax_totals'  => $taxTotals,
                'delivery'    => [
                    'actualDeliveryDate' => $issueDate,
                    'carrier_name'       => $carrierName,
                    'carrier_vkn'        => $carrierVkn,
                ],
                'order_reference' => [
                    'id'         => (string)($order->order_number ?? $order->id),
                    'issue_date' => $issueDate,
                ],
                'internet_sale' => [
                    'webAddress'  => 'https://www.trendyol.com',
                    'paymentType' => 'ODEMEARACISI',
                    'platform'    => 'Trendyol',
                    'paymentDate' => $issueDate,
                ],
            ];
            if (config('efatura_ai.debug')) {
                // Debug: Delivery payload
                Log::info('[EArchiveInvoiceService] Delivery payload', [
                    'order_id' => $order->id,
                    'carrier_name' => $carrierName,
                    'carrier_vkn_db' => $carrierVknDb,
                    'carrier_vkn_map'=> $carrierVknMap,
                    'carrier_vkn' => $carrierVkn,
                    'delivery' => $invoiceData['delivery'],
                ]);
            }
            $xml = $this->ublBuilder->build($invoiceData);
            $this->logUblBuildAll($order, 'TY', $invoiceData, $xml);
            if (config('efatura_ai.debug')) {
                // Debug: Delivery XML snippet
                $deliveryStart = strpos($xml, '<cac:Delivery>');
                $deliverySnippet = $deliveryStart !== false ? substr($xml, $deliveryStart, 600) : null;
                Log::info('[EArchiveInvoiceService] Delivery XML snippet', [
                    'order_id' => $order->id,
                    'has_delivery' => $deliveryStart !== false,
                    'snippet' => $deliverySnippet,
                ]);
                // Debug: INTERNET_SATIS AdditionalDocumentReference snippet
                $inetIdPos = strpos($xml, '<cbc:ID>INTERNET_SATIS</cbc:ID>');
                if ($inetIdPos !== false) {
                    // Bulunan ID etiketinden önceki IssuerParty başlangıcını tahmini alalım
                    $issuerStart = strrpos(substr($xml, 0, $inetIdPos), '<cac:IssuerParty>');
                    $inetSnippet = $issuerStart !== false ? substr($xml, $issuerStart, 800) : substr($xml, $inetIdPos, 800);
                    Log::info('[EArchiveInvoiceService] INTERNET_SATIS IssuerParty snippet', [
                        'order_id' => $order->id,
                        'snippet' => $inetSnippet,
                    ]);
                } else {
                    Log::info('[EArchiveInvoiceService] INTERNET_SATIS not present', [ 'order_id' => $order->id ]);
                }
            }
            $this->provider->initForCustomer($integration);
            $documentPayload = [
                'xml' => $xml,
                'documentUUID' => $uuid,
                'documentId' => $documentId,
                'documentDate' => $invoiceData['issue_date'],
                'note' => implode('; ', $invoiceData['notes']),
            ];
            if ($isEFatura) {
                // e-Fatura: destinationUrn = alıcı e-posta (ham veri veya siparişteki email)
                $destinationEmail = (string)($order->customer_email ?? ($buyerNode['email'] ?? ''));
                if ($destinationEmail !== '') { $documentPayload['destinationUrn'] = $destinationEmail; }
                // sourceUrn provider içinde integration'dan alınır (credentials.source_urn)
                return $this->provider->sendEInvoice($documentPayload);
            }
            return $this->provider->sendEArchiveInvoice($documentPayload);

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Servis hatası: ' . $e->getMessage()];
        }
    }

    public function sendFromWcOrder(int $orderId): array
    {
        try {
            $order = \App\Models\WcOrder::with('customer.invoiceIntegrations')->findOrFail($orderId);
            $customer = $order->customer;
            $integration = $customer->invoiceIntegrations()->where('provider', 'efatura_ai')->first();
            if (!$integration) { throw new Exception('Müşteri için aktif Efatura.ai entegrasyonu bulunamadı.'); }

            $supplier = [
                'name'        => (string)($customer->getMeta('company_name', false) ?: $customer->name),
                'vkn_tckn'    => (string)($customer->getMeta('tax_number', false) ?: ''),
                'tax_office'  => (string)($customer->getMeta('tax_office', false) ?: ''),
                'street'      => (string)($customer->getMeta('company_address', false) ?: ''),
                'district'    => (string)($customer->getMeta('company_district', false) ?: ''),
                'city'        => (string)($customer->getMeta('company_city', false) ?: ''),
                'postal_zone' => (string)($customer->getMeta('postal_code', false) ?: ''),
                'country'     => 'Türkiye', 'phone' => '', 'email' => ''
            ];
            if (trim((string)$supplier['vkn_tckn']) === '') { throw new Exception('Gönderici VKN/TCKN eksik.'); }

            $invAddr = (array)($order->billing ?? []);
            $billingEmail = (string)($invAddr['email'] ?? '');
            // WC: bireysel/kurumsal ayrımı yok → 11 haneli yoksa e-Arşiv
            $rawVkn = (string)($invAddr['tax_number'] ?? '');
            $rawTckn = (string)($invAddr['tcIdentityNumber'] ?? '');
            $buyerVknTckn = '';
            $isEFatura = false;
            if ($rawVkn !== '' && preg_match('/^\d{10}$/', $rawVkn)) { $buyerVknTckn = $rawVkn; $isEFatura = true; }
            elseif ($rawTckn !== '' && preg_match('/^\d{11}$/', $rawTckn)) { $buyerVknTckn = $rawTckn; $isEFatura = true; }
            if ($buyerVknTckn === '') { $buyerVknTckn = '11111111111'; $isEFatura = false; }

            $buyerFirst = (string)($invAddr['first_name'] ?? '');
            $buyerLast  = (string)($invAddr['last_name'] ?? '');
            $buyerName  = trim($buyerFirst . ' ' . $buyerLast);
            $street     = $this->resolveStreetFromAddress([
                'address' => trim((string)($invAddr['address'] ?? ''))
            ]);
            $postal     = (string)($invAddr['postal_code'] ?? '');
            $buyer = [
                'vkn_tckn'   => $buyerVknTckn,
                'name'       => $buyerName,
                'first_name' => $buyerFirst,
                'family_name'=> $buyerLast,
                'street'     => $street,
                'district'   => (string)($invAddr['district'] ?? ''),
                'city'       => (string)($invAddr['city'] ?? ''),
                'postal_zone'=> $postal,
                'email'      => $billingEmail,
                'tax_office' => ''
            ];

            $items = (array)($order->items ?? []);
            $lines = []; $taxGroup = []; $lineExt = 0.0; $taxTotal = 0.0;
            foreach ($items as $it) {
                $qty = (float)($it['quantity'] ?? 1);
                $unit = 'C62';
                $priceGross = (float)($it['price'] ?? 0);
                $rate = (float)($it['vat_rate'] ?? 0);
                $mult = 1.0 + ($rate/100.0);
                $lineGross = $qty * $priceGross;
                $lineNet   = $mult > 0 ? ($lineGross / $mult) : $lineGross;
                $vat       = $lineGross - $lineNet;
                $unitNet   = $mult > 0 ? ($priceGross / $mult) : $priceGross;
                $lineExt  += $lineNet; $taxTotal += $vat;
                $lines[] = [
                    'name' => (string)($it['name'] ?? 'Ürün'),
                    'quantity' => $qty, 'unit' => $unit,
                    'unit_price' => $unitNet, 'vat_rate' => $rate,
                    'sku' => (string)($it['sku'] ?? '')
                ];
                $key=(string)$rate; if(!isset($taxGroup[$key])){$taxGroup[$key]=['rate'=>$rate,'taxable'=>0.0,'tax'=>0.0];}
                $taxGroup[$key]['taxable']+=$lineNet; $taxGroup[$key]['tax']+=$vat;
            }
            $taxTotals = array_values($taxGroup);
            $taxExclusive = $lineExt; $taxInclusive = $lineExt + $taxTotal;
            $currency = (string)($order->currency ?? 'TRY');
            $issueDate= $order->order_date ? $order->order_date->format('Y-m-d') : date('Y-m-d');
            $uuid = (string) Str::uuid();
            $profileId = $isEFatura ? 'TEMELFATURA' : 'EARSIVFATURA';
            $documentId = $this->nextDocumentIdOrFail($integration);

            $invoiceData = [
                'uuid'=>$uuid,'id'=>$documentId,'profile_id'=>$profileId,'type_code'=>'SATIS','issue_date'=>$issueDate,'issue_time'=>date('H:i:s'),'currency'=>$currency,
                'notes'=>['İrsaliye yerine geçer.'],'supplier'=>$supplier,'customer'=>$buyer,'lines'=>$lines,
                'totals'=>['line_extension'=>$taxExclusive,'tax_exclusive'=>$taxExclusive,'tax_inclusive'=>$taxInclusive,'allowance_total'=>0,'payable'=>$taxInclusive],
                'tax_totals'=>$taxTotals,
                'delivery'=>['actualDeliveryDate'=>$issueDate,'carrier_name'=>'','carrier_vkn'=>''],
                'order_reference'=>['id'=>(string)($order->number ?? $order->id),'issue_date'=>$issueDate],
                'internet_sale'=>['webAddress'=>'https://woocommerce','paymentType'=>'ODEMEARACISI','platform'=>'WooCommerce','paymentDate'=>$issueDate]
            ];
            $xml=$this->ublBuilder->build($invoiceData);
            $this->logUblBuildAll($order, 'WC', $invoiceData, $xml);
            $this->provider->initForCustomer($integration);
            $payload=['xml'=>$xml,'documentUUID'=>$uuid,'documentId'=>$documentId,'documentDate'=>$invoiceData['issue_date'],'note'=>implode('; ',$invoiceData['notes'])];
            if ($isEFatura) {
                if ($billingEmail !== '') { $payload['destinationUrn'] = $billingEmail; }
                return $this->provider->sendEInvoice($payload);
            }
            return $this->provider->sendEArchiveInvoice($payload);
        } catch (Exception $e) {
            return ['success'=>false,'message'=>'Servis hatası: '.$e->getMessage()];
        }
    }

    public function sendFromHepsiburadaOrder(int $orderId): array
    {
        try {
            $order = \App\Models\HepsiburadaOrder::with('items', 'customer.invoiceIntegrations')->findOrFail($orderId);
            // Trendyol akışıyla aynı; alan eşlemeleri Hepsiburada modelindeki isimlere göre yukarıdaki blokta uyarlanmalı.
            // Basit uyarlama: isimler farklıysa mapping yap.
            // Hepsiburada: buyer_first_name, buyer_last_name, buyer_email; billing_address JSON
            $customer = $order->customer;
            $integration = $customer->invoiceIntegrations()->where('provider', 'efatura_ai')->first();
            if (!$integration) { throw new Exception('Müşteri için aktif Efatura.ai entegrasyonu bulunamadı.'); }

            $supplier = [
                'name' => (string)($customer->getMeta('company_name', false) ?: $customer->name),
                'vkn_tckn' => (string)($customer->getMeta('tax_number', false) ?: ''),
                'tax_office' => (string)($customer->getMeta('tax_office', false) ?: ''),
                'street' => (string)($customer->getMeta('company_address', false) ?: ''),
                'district' => (string)($customer->getMeta('company_district', false) ?: ''),
                'city' => (string)($customer->getMeta('company_city', false) ?: ''),
                'postal_zone' => (string)($customer->getMeta('postal_code', false) ?: ''),
                'country' => 'Türkiye', 'phone' => '', 'email' => ''
            ];
            $invAddr = (array)($order->billing_address ?? []);
            $rawData = (array)($order->raw_data ?? []);
            $buyerNode = (array)($rawData['details']['buyer'] ?? []);
            // Öncelik: raw_data.details.buyer.tax_number -> billing_address.taxNumber -> TCKN (tcId)
            $rawVkn = (string)($buyerNode['tax_number'] ?? ($invAddr['taxNumber'] ?? ''));
            $rawTckn = (string)($invAddr['tcId'] ?? '');
            $buyerVknTckn = $rawVkn !== '' ? $rawVkn : $rawTckn;
            $isEFatura = $rawVkn !== '' && preg_match('/^\d{10}$/', $rawVkn) === 1;
            if ($buyerVknTckn === '') { $buyerVknTckn = '11111111111'; }
            $buyerFirst = (string)($order->buyer_first_name ?? '');
            $buyerLast  = (string)($order->buyer_last_name ?? '');
            $buyerName  = trim($buyerFirst . ' ' . $buyerLast);
            $streetHb   = $this->resolveStreetFromAddress($invAddr);
            $postalHb   = $this->resolvePostalFromAddress($invAddr);
            $buyer = [
                'vkn_tckn' => $buyerVknTckn,
                'name' => $buyerName,
                'first_name' => $buyerFirst,
                'family_name'=> $buyerLast,
                'street' => $streetHb,
                'district' => (string)($invAddr['district'] ?? ''),
                'city' => (string)($invAddr['city'] ?? ''),
                'postal_zone' => $postalHb,
                'email' => (string)($order->buyer_email ?? ''),
                'tax_office' => (string)($invAddr['taxOffice'] ?? '')
            ];
            // Satır ve fiyatlar: Hepsiburada item modeline göre (varsayım: KDV dahil total)
            $lines = [];
            $taxGroup = []; $lineExt = 0.0; $taxTotal = 0.0;
            foreach ($order->items as $it) {
                $qty = (float)($it->quantity ?? 1);
                $unit = 'C62';
                $priceGross = (float)($it->total_price ?? $it->price ?? 0);
                $rate = (float)($it->vat_rate ?? 0);
                $mult = 1.0 + ($rate/100.0);
                $lineGross = $qty * $priceGross;
                $lineNet = $mult > 0 ? ($lineGross/$mult) : $lineGross;
                $vat = $lineGross - $lineNet;
                $unitNet = $mult > 0 ? ($priceGross/$mult) : $priceGross;
                $lineExt += $lineNet; $taxTotal += $vat;
                $lineName = (string)($this->resolveItemName('hb', (int)$customer->id, $it));
                $lines[] = ['name'=>$lineName,'quantity'=>$qty,'unit'=>$unit,'unit_price'=>$unitNet,'vat_rate'=>$rate,'sku'=>(string)($it->sku ?? '')];
                $key=(string)$rate; if(!isset($taxGroup[$key])){$taxGroup[$key]=['rate'=>$rate,'taxable'=>0.0,'tax'=>0.0];}
                $taxGroup[$key]['taxable']+=$lineNet; $taxGroup[$key]['tax']+=$vat;
            }
            $taxTotals = array_values($taxGroup);
            $taxExclusive = $lineExt; $taxInclusive = $lineExt + $taxTotal;
            $currency = (string)($order->currency ?? 'TRY');
            $issueDate = $order->order_date ? $order->order_date->format('Y-m-d') : date('Y-m-d');
            $uuid=(string)Str::uuid(); $profileId = $isEFatura ? 'TEMELFATURA' : 'EARSIVFATURA';
            // DocumentId kullanıcı seri etiketi üzerinden
            $documentId = $this->nextDocumentIdOrFail($integration);
            $carrierName=trim((string)($order->shipping_provider_name ?? ''));
            $carrierVknDb=''; $carrierVknMap=$carrierName!==''?(string)(config('shipping_carriers.'.strtolower($carrierName))??''):''; $carrierVkn=$carrierVknDb!==''?$carrierVknDb:$carrierVknMap;
            $invoiceData=[
                'uuid'=>$uuid,'id'=>$documentId,'profile_id'=>$profileId,'type_code'=>'SATIS','issue_date'=>$issueDate,'issue_time'=>date('H:i:s'),'currency'=>$currency,
                'notes'=>['İrsaliye yerine geçer.'],'supplier'=>$supplier,'customer'=>$buyer,'lines'=>$lines,
                'totals'=>['line_extension'=>$taxExclusive,'tax_exclusive'=>$taxExclusive,'tax_inclusive'=>$taxInclusive,'allowance_total'=>0,'payable'=>$taxInclusive],
                'tax_totals'=>$taxTotals,
                'delivery'=>['actualDeliveryDate'=>$issueDate,'carrier_name'=>$carrierName,'carrier_vkn'=>$carrierVkn],
                'order_reference'=>['id'=>(string)($order->order_id ?? $order->id),'issue_date'=>$issueDate],
                'internet_sale'=>['webAddress'=>'https://www.hepsiburada.com','paymentType'=>'ODEMEARACISI','platform'=>'Hepsiburada','paymentDate'=>$issueDate]
            ];
            $xml=$this->ublBuilder->build($invoiceData); $this->logUblBuildAll($order, 'HB', $invoiceData, $xml); $this->provider->initForCustomer($integration);
            $documentPayload=['xml'=>$xml,'documentUUID'=>$uuid,'documentId'=>$documentId,'documentDate'=>$invoiceData['issue_date'],'note'=>implode('; ',$invoiceData['notes'])];
            if ($isEFatura) {
                $destinationEmail = (string)($order->buyer_email ?? ($buyerNode['email'] ?? ''));
                if ($destinationEmail !== '') { $documentPayload['destinationUrn'] = $destinationEmail; }
                return $this->provider->sendEInvoice($documentPayload);
            }
            return $this->provider->sendEArchiveInvoice($documentPayload);
        } catch (Exception $e) { return ['success'=>false,'message'=>'Servis hatası: '.$e->getMessage()]; }
    }

    public function sendFromN11Order(int $orderId): array
    {
        try {
            $order = \App\Models\N11Order::with('items', 'customer.invoiceIntegrations')->findOrFail($orderId);
            $customer = $order->customer; $integration=$customer->invoiceIntegrations()->where('provider','efatura_ai')->first(); if(!$integration){throw new Exception('Müşteri için aktif Efatura.ai entegrasyonu bulunamadı.');}
            $supplier=['name'=>(string)($customer->getMeta('company_name',false)?:$customer->name),'vkn_tckn'=>(string)($customer->getMeta('tax_number',false)?:''),'tax_office'=>(string)($customer->getMeta('tax_office',false)?:''),'street'=>(string)($customer->getMeta('company_address',false)?:''),'district'=>(string)($customer->getMeta('company_district',false)?:''),'city'=>(string)($customer->getMeta('company_city',false)?:''),'postal_zone'=>(string)($customer->getMeta('postal_code',false)?:''),'country'=>'Türkiye','phone'=>'','email'=>''];
            $invAddr=(array)($order->billing_address ?? []);
            $rawData = (array)($order->raw_data ?? []);
            $buyerNode = (array)($rawData['details']['buyer'] ?? []);
            // Öncelik: raw_data.addresses.billing.taxId -> raw_data.details.buyer.tax_number -> billing_address.taxNumber -> TCKN
            $rawVkn=(string)($rawData['details']['addresses']['billing']['taxId'] ?? ($buyerNode['tax_number'] ?? ($invAddr['taxNumber'] ?? '')));
            $rawTckn=(string)($order->customer_tc_id ?? '');
            $buyerVknTckn=$rawVkn!==''?$rawVkn:$rawTckn; $isEFatura=$rawVkn!==''&&preg_match('/^\d{10}$/',$rawVkn)===1; if($buyerVknTckn===''){ $buyerVknTckn='11111111111'; }
            $buyerName=(string)($order->customer_full_name ?? '');
            // İsimleri ayır: ilk kelime ad, kalanlar soyad
            $firstName=''; $familyName='';
            if ($buyerName !== '') {
                $parts = preg_split('/\s+/', trim($buyerName));
                if ($parts && count($parts) > 0) {
                    $firstName = array_shift($parts);
                    $familyName = trim(implode(' ', $parts));
                }
            }
            $streetN11 = $this->resolveStreetFromAddress($invAddr);
            $postalN11 = $this->resolvePostalFromAddress($invAddr);
            $buyer=['vkn_tckn'=>$buyerVknTckn,'name'=>$buyerName,'first_name'=>$firstName,'family_name'=>$familyName,'street'=>(string)($invAddr['fullAddress'] ?? ''),'district'=>(string)($invAddr['district'] ?? ''),'city'=>(string)($invAddr['city'] ?? ''),'email'=>(string)($order->customer_email ?? ''),'tax_office'=>(string)($invAddr['taxOffice'] ?? '')];
            $buyer['street'] = $streetN11; $buyer['postal_zone'] = $postalN11;
            $lines=[]; $taxGroup=[]; $lineExt=0.0; $taxTotal=0.0;
            foreach($order->items as $it){ $qty=(float)($it->quantity ?? 1); $unit='C62'; $priceGross=(float)($it->amount ?? $it->price ?? 0); $rate=(float)($it->vat_rate ?? 0); $mult=1.0+($rate/100.0); $lineGross=$qty*$priceGross; $lineNet=$mult>0?($lineGross/$mult):$lineGross; $vat=$lineGross-$lineNet; $unitNet=$mult>0?($priceGross/$mult):$priceGross; $lineExt+=$lineNet; $taxTotal+=$vat; $lines[]=['name'=>(string)($this->resolveItemName('n11', (int)$customer->id, $it)),'quantity'=>$qty,'unit'=>$unit,'unit_price'=>$unitNet,'vat_rate'=>$rate,'sku'=>(string)($it->sku ?? '')]; $key=(string)$rate; if(!isset($taxGroup[$key])){$taxGroup[$key]=['rate'=>$rate,'taxable'=>0.0,'tax'=>0.0];} $taxGroup[$key]['taxable']+=$lineNet; $taxGroup[$key]['tax']+=$vat; }
            $taxTotals=array_values($taxGroup); $taxExclusive=$lineExt; $taxInclusive=$lineExt+$taxTotal; $currency=(string)($order->currency_code ?? 'TRY'); $issueDate=$order->order_date?$order->order_date->format('Y-m-d'):date('Y-m-d'); $uuid=(string)Str::uuid(); $profileId=$isEFatura?'TEMELFATURA':'EARSIVFATURA';
            // DocumentId kullanıcı seri etiketi üzerinden
            $documentId = $this->nextDocumentIdOrFail($integration);
            $carrierName=trim((string)($order->cargo_provider_name ?? '')); $carrierVknDb=''; $carrierVknMap=$this->resolveCarrierVkn($carrierName); $carrierVkn=$carrierVknDb!==''?$carrierVknDb:$carrierVknMap;
            $carrierName=trim((string)($order->cargo_provider_name ?? ''));
            if ($carrierName === '' && is_array($order->raw_data ?? null)) {
                $firstItem = $order->raw_data['details']['items'][0]['shipment_info']['shipment_company']['name'] ?? null;
                if (is_string($firstItem) && $firstItem !== '') { $carrierName = $firstItem; }
            }
            $carrierVknDb=''; $carrierVknMap=$this->resolveCarrierVkn($carrierName); $carrierVkn=$carrierVknDb!==''?$carrierVknDb:$carrierVknMap;
            $carrierName=trim((string)($order->cargo_provider_name ?? '')); $carrierVknDb=''; $carrierVknMap=$this->resolveCarrierVkn($carrierName); $carrierVkn=$carrierVknDb!==''?$carrierVknDb:$carrierVknMap;
            $invoiceData=['uuid'=>$uuid,'id'=>$documentId,'profile_id'=>$profileId,'type_code'=>'SATIS','issue_date'=>$issueDate,'issue_time'=>date('H:i:s'),'currency'=>$currency,'notes'=>['İrsaliye yerine geçer.'],'supplier'=>$supplier,'customer'=>$buyer,'lines'=>$lines,'totals'=>['line_extension'=>$taxExclusive,'tax_exclusive'=>$taxExclusive,'tax_inclusive'=>$taxInclusive,'allowance_total'=>0,'payable'=>$taxInclusive],'tax_totals'=>$taxTotals,'delivery'=>['actualDeliveryDate'=>$issueDate,'carrier_name'=>$carrierName,'carrier_vkn'=>$carrierVkn],'order_reference'=>['id'=>(string)($order->order_number ?? $order->id),'issue_date'=>$issueDate],'internet_sale'=>['webAddress'=>'https://www.n11.com','paymentType'=>'ODEMEARACISI','platform'=>'N11','paymentDate'=>$issueDate]];
            $xml=$this->ublBuilder->build($invoiceData); $this->logUblBuildAll($order, 'N11', $invoiceData, $xml); $this->provider->initForCustomer($integration); $documentPayload=['xml'=>$xml,'documentUUID'=>$uuid,'documentId'=>$documentId,'documentDate'=>$invoiceData['issue_date'],'note'=>implode('; ',$invoiceData['notes'])];
            if ($isEFatura) {
                $destinationEmail = (string)($order->customer_email ?? ($buyerNode['email'] ?? ''));
                if ($destinationEmail !== '') { $documentPayload['destinationUrn'] = $destinationEmail; }
                return $this->provider->sendEInvoice($documentPayload);
            }
            return $this->provider->sendEArchiveInvoice($documentPayload);
        } catch (Exception $e) { return ['success'=>false,'message'=>'Servis hatası: '.$e->getMessage()]; }
    }

    public function sendFromLbOrder(int $orderId): array
    {
        try{
            $order=\App\Models\LbOrder::with('items','customer.invoiceIntegrations')->findOrFail($orderId); $customer=$order->customer; $integration=$customer->invoiceIntegrations()->where('provider','efatura_ai')->first(); if(!$integration){throw new Exception('Müşteri için aktif Efatura.ai entegrasyonu bulunamadı.');}
            $supplier=['name'=>(string)($customer->getMeta('company_name',false)?:$customer->name),'vkn_tckn'=>(string)($customer->getMeta('tax_number',false)?:''),'tax_office'=>(string)($customer->getMeta('tax_office',false)?:''),'street'=>(string)($customer->getMeta('company_address',false)?:''),'district'=>(string)($customer->getMeta('company_district',false)?:''),'city'=>(string)($customer->getMeta('company_city',false)?:''),'postal_zone'=>(string)($customer->getMeta('postal_code',false)?:''),'country'=>'Türkiye','phone'=>'','email'=>''];
            $rawData = (array)($order->raw_data ?? []);
            $buyerNode = (array)($rawData['details']['buyer'] ?? []);
            $addrNode  = (array)($rawData['details']['addresses']['billing'] ?? []);
            // Öncelik: raw_data.details.buyer.tax_number -> model.billing_tax_number -> raw_data.details.addresses.billing.identity -> customer_tckn
            $rawVkn=(string)($buyerNode['tax_number'] ?? ($order->billing_tax_number ?? ($addrNode['identity'] ?? '')));
            $rawTckn=(string)($order->customer_tckn ?? '');
            $buyerVknTckn=$rawVkn!==''?$rawVkn:$rawTckn; $isEFatura=$rawVkn!==''&&preg_match('/^\d{10}$/',$rawVkn)===1; if($buyerVknTckn===''){ $buyerVknTckn='11111111111'; }
            $buyerName=(string)($order->customer_name ?? '');
            $firstNameLb=''; $familyNameLb='';
            if ($buyerName !== '') {
                $parts = preg_split('/\s+/', trim($buyerName));
                if ($parts && count($parts) > 0) {
                    $firstNameLb = array_shift($parts);
                    $familyNameLb = trim(implode(' ', $parts));
                }
            }
            $buyer=['vkn_tckn'=>$buyerVknTckn,'name'=>$buyerName,'first_name'=>$firstNameLb,'family_name'=>$familyNameLb,'street'=>$this->resolveStreetFromAddress($order->billing_address ?? ''),'district'=>(string)($order->billing_district ?? ''),'city'=>(string)($order->billing_city ?? ''),'postal_zone'=>(string)($order->billing_postal_code ?? ''),'email'=>(string)($order->customer_email ?? ''),'tax_office'=>(string)($order->billing_tax_office ?? '')];
            $lines=[]; $taxGroup=[]; $lineExt=0.0; $taxTotal=0.0; foreach($order->items as $it){ $qty=(float)($it->quantity ?? 1); $unit='C62'; $priceGross=(float)($it->price ?? 0); $rate=(float)($it->vat_rate ?? 0); $mult=1.0+($rate/100.0); $lineGross=$qty*$priceGross; $lineNet=$mult>0?($lineGross/$mult):$lineGross; $vat=$lineGross-$lineNet; $unitNet=$mult>0?($priceGross/$mult):$priceGross; $lineExt+=$lineNet; $taxTotal+=$vat; $lines[]=['name'=>(string)($this->resolveItemName('lb', (int)$customer->id, $it)),'quantity'=>$qty,'unit'=>$unit,'unit_price'=>$unitNet,'vat_rate'=>$rate,'sku'=>(string)($it->sku ?? '')]; $key=(string)$rate; if(!isset($taxGroup[$key])){$taxGroup[$key]=['rate'=>$rate,'taxable'=>0.0,'tax'=>0.0];} $taxGroup[$key]['taxable']+=$lineNet; $taxGroup[$key]['tax']+=$vat; }
            $taxTotals=array_values($taxGroup); $taxExclusive=$lineExt; $taxInclusive=$lineExt+$taxTotal; $currency=(string)($order->currency ?? 'TRY'); $issueDate=$order->order_date?$order->order_date->format('Y-m-d'):date('Y-m-d'); $uuid=(string)Str::uuid(); $profileId=$isEFatura?'TEMELFATURA':'EARSIVFATURA'; $documentId = $this->nextDocumentIdOrFail($integration); $carrierName=trim((string)($order->cargo_provider_name ?? '')); $carrierVknDb=''; $carrierVknMap=$carrierName!==''?(string)(config('shipping_carriers.'.strtolower($carrierName))??''):''; $carrierVkn=$carrierVknDb!==''?$carrierVknDb:$carrierVknMap;
            $invoiceData=['uuid'=>$uuid,'id'=>$documentId,'profile_id'=>$profileId,'type_code'=>'SATIS','issue_date'=>$issueDate,'issue_time'=>date('H:i:s'),'currency'=>$currency,'notes'=>['İrsaliye yerine geçer.'],'supplier'=>$supplier,'customer'=>$buyer,'lines'=>$lines,'totals'=>['line_extension'=>$taxExclusive,'tax_exclusive'=>$taxExclusive,'tax_inclusive'=>$taxInclusive,'allowance_total'=>0,'payable'=>$taxInclusive],'tax_totals'=>$taxTotals,'delivery'=>['actualDeliveryDate'=>$issueDate,'carrier_name'=>$carrierName,'carrier_vkn'=>$carrierVkn],'order_reference'=>['id'=>(string)($order->order_number ?? $order->id),'issue_date'=>$issueDate],'internet_sale'=>['webAddress'=>'https://www.lazimbana.com','paymentType'=>'ODEMEARACISI','platform'=>'Lazımbana','paymentDate'=>$issueDate]];
            $xml=$this->ublBuilder->build($invoiceData); $this->logUblBuildAll($order, 'LB', $invoiceData, $xml); $this->provider->initForCustomer($integration); $documentPayload=['xml'=>$xml,'documentUUID'=>$uuid,'documentId'=>$documentId,'documentDate'=>$invoiceData['issue_date'],'note'=>implode('; ',$invoiceData['notes'])];
            if ($isEFatura) {
                $destinationEmail = (string)($order->customer_email ?? ($buyerNode['email'] ?? ''));
                if ($destinationEmail !== '') { $documentPayload['destinationUrn'] = $destinationEmail; }
                return $this->provider->sendEInvoice($documentPayload);
            }
            return $this->provider->sendEArchiveInvoice($documentPayload);
        } catch (Exception $e) { return ['success'=>false,'message'=>'Servis hatası: '.$e->getMessage()]; }
    }
}


