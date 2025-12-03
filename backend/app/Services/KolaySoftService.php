<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class KolaySoftService
{
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $sourceUrn;
    protected $supplierVknTckn;
    protected $supplierName;
    protected $ublGenerator;

    public function __construct(UblGenerator $ublGenerator)
    {
        // Default değerler (Fallback - .env'den veya config'den)
        $baseUrl = config('services.kolaysoft.base_url');
        if ($baseUrl) {
            $parsed = parse_url($baseUrl);
            if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
                $this->baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
                if (isset($parsed['port'])) {
                    $this->baseUrl .= ':' . $parsed['port'];
                }
            } else {
                $baseUrl = preg_replace('/\?.*$/', '', $baseUrl);
                $baseUrl = preg_replace('#(/EArchiveInvoiceService.*|/InvoiceService.*)$#', '', $baseUrl);
                $this->baseUrl = rtrim($baseUrl, '/');
            }
        } else {
            $this->baseUrl = 'https://servis.kolayentegrasyon.net'; // Default
        }
        
        // Default değerler (Fallback)
        $this->username = config('services.kolaysoft.username');
        $this->password = config('services.kolaysoft.password');
        $this->supplierVknTckn = config('services.kolaysoft.supplier_vkn_tckn');
        $this->supplierName = config('services.kolaysoft.supplier_name', 'SaaS Magaza A.S.');
        $this->sourceUrn = config('services.kolaysoft.source_urn', 'urn:mail:defaultpk');
        $this->ublGenerator = $ublGenerator;
    }

    /**
     * Mağaza ayarlarını yükler (Store modelinden).
     */
    protected function loadStoreSettings(?Store $store = null)
    {
        if ($store) {
            // Store'dan ayarları al
            if ($store->kolaysoft_username) {
                $this->username = $store->kolaysoft_username;
            }
            if ($store->kolaysoft_password) {
                $this->password = $store->kolaysoft_password;
            }
            if ($store->kolaysoft_vkn_tckn) {
                $this->supplierVknTckn = $store->kolaysoft_vkn_tckn;
            }
            if ($store->kolaysoft_supplier_name) {
                $this->supplierName = $store->kolaysoft_supplier_name;
            }
        }
    }

    public function createInvoice(Order $order, ?Store $store = null)
    {
        // Store ayarlarını yükle (eğer verilmişse)
        $this->loadStoreSettings($store);
        
        // Eğer Store yoksa, Order'dan User üzerinden Store bulmayı dene
        if (!$store && $order->user) {
            $store = $order->user->stores()->first();
            if ($store) {
                $this->loadStoreSettings($store);
            }
        }
        
        // 1. Basic Definitions
        $action = 'sendInvoice';
        // Namespace for E-Archive (küçük harf - örnek kodda böyle)
        $service = 'EArchiveInvoiceService'; 
        $serviceWs = 'EArchiveInvoiceWS';
        $namespace = 'http://earchiveinvoiceservice.entegrator.com/';
        // URL formatı: base_url/service/serviceWs (örnek kodda böyle)
        $serviceUrl = $this->baseUrl . '/' . $service . '/' . $serviceWs;
        $uuid = Str::uuid()->toString();

        try {
            // 1. documentId'yi önce oluştur (UBL XML'deki cbc:ID ile eşleşmesi için)
            $documentId = $order->invoice_number ?? '';
            if ($documentId === '') {
                $prefix = 'ZNP';
                $year = date('Y');
                $number = str_pad($order->id ?? 1, 9, '0', STR_PAD_LEFT);
                $documentId = $prefix . $year . $number;
            }
            if (!preg_match('/^[A-Z]{3}\d{4}\d{9,}$/', $documentId)) {
                $prefix = 'ZNP';
                $year = date('Y');
                $number = str_pad($order->id ?? 1, 9, '0', STR_PAD_LEFT);
                $documentId = $prefix . $year . $number;
            }
            
            // 2. Generate and Clean UBL XML (documentId'yi geçir - cbc:ID ile eşleşmesi için)
            // Store ayarlarından supplier bilgilerini geçir
            $xmlContent = $this->ublGenerator->generate(
                $order, 
                $uuid, 
                $documentId,
                $this->supplierVknTckn,
                $this->supplierName
            );
            
            if ($xmlContent !== '') {
                $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent); // Remove BOM
                $xmlContent = preg_replace('/^<\?xml[^>]*?\?>/s', '', $xmlContent); // Remove Declaration
                $xmlContent = ltrim($xmlContent);
                
                // XML Validation - Hatalı XML'i yakala
                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                if (!$dom->loadXML($xmlContent)) {
                    $errors = libxml_get_errors();
                    $errorMsg = 'XML Validation Error: ';
                    foreach ($errors as $error) {
                        $errorMsg .= trim($error->message) . ' ';
                    }
                    libxml_clear_errors();
                    Log::error("UBL XML Validation Failed: " . $errorMsg);
                    return ['success' => false, 'message' => $errorMsg];
                }
            }

            // 3. Build Inner XML (E-Arşiv için invoiceXMLList kullanılmalı - örnek kodda böyle)
            // E-Arşiv için sourceUrn ve destinationUrn GÖNDERİLMEMELİ
            // Sıralama: xmlContent, documentUUID, documentId, documentDate, note (örnek kodda böyle)
            
            $innerXml = '<invoiceXMLList>';
            
            // 1. xmlContent (ilk sırada - CDATA içinde)
            $innerXml .= '<xmlContent><![CDATA[' . $xmlContent . ']]></xmlContent>';
            
            // 2. documentUUID
            $innerXml .= '<documentUUID>' . htmlspecialchars($uuid, ENT_XML1) . '</documentUUID>';
            
            // 3. documentId (zaten yukarıda oluşturuldu, sadece XML'e ekle)
            $innerXml .= '<documentId>' . htmlspecialchars($documentId, ENT_XML1) . '</documentId>';
            
            // 4. documentDate
            $innerXml .= '<documentDate>' . htmlspecialchars(now()->format('Y-m-d'), ENT_XML1) . '</documentDate>';
            
            // 5. note (opsiyonel)
            $note = 'Sipariş No: ' . $order->order_number;
            $innerXml .= '<note>' . htmlspecialchars($note, ENT_XML1) . '</note>';
            
            $innerXml .= '</invoiceXMLList>';

            // 4. Build SOAP Envelope (örnek kod formatı)
            $envelope = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ear="' . $namespace . '">';
            $envelope .= '<soapenv:Header/>';
            $envelope .= '<soapenv:Body>';
            $envelope .= '<ear:' . $action . '>';
            $envelope .= $innerXml;
            $envelope .= '</ear:' . $action . '>';
            $envelope .= '</soapenv:Body>';
            $envelope .= '</soapenv:Envelope>';

            // 5. Prepare Headers (örnek kod formatı)
            $headers = [
                'Content-Type: text/xml; charset=utf-8',
                'Accept: text/xml',
                'Username: ' . ($this->username ?? ''),
                'Password: ' . ($this->password ?? ''),
                'SOAPAction: ""', // Boş string - örnek kodda böyle
            ];

            // Log Request Details
            $maskedHeaders = array_map(function($header) {
                return preg_replace('/Password: (.*)/', 'Password: *****', $header);
            }, $headers);
            
            Log::info('[KolaySoft] Fatura Gönderimi Başlatıldı', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'document_uuid' => $uuid,
                'service_url' => $serviceUrl,
                'action' => $action,
                'headers' => $maskedHeaders,
                'request_body_length' => strlen($envelope),
                'request_body_preview' => substr($envelope, 0, 500) . '...',
                'full_request_body' => $envelope,
            ]);

            // 6. Send cURL Request (örnek kod formatı)
            $ch = curl_init($serviceUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $envelope);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_VERBOSE, true); // Debug için

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);

            // Log Response Details
            Log::info('[KolaySoft] Yanıt Alındı', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'http_code' => $httpCode,
                'curl_error' => $curlError ?: null,
                'response_body_length' => strlen($responseBody),
                'response_body_preview' => substr($responseBody, 0, 500) . '...',
                'full_response_body' => $responseBody,
                'curl_info' => [
                    'total_time' => $curlInfo['total_time'] ?? null,
                    'connect_time' => $curlInfo['connect_time'] ?? null,
                    'size_download' => $curlInfo['size_download'] ?? null,
                ],
            ]);

            if ($curlError) {
                Log::error('[KolaySoft] cURL Hatası', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'curl_error' => $curlError,
                    'http_code' => $httpCode,
                ]);
                return ['success' => false, 'message' => 'cURL Error: ' . $curlError, 'http_code' => $httpCode];
            }

            // 7. Parse Response (örnek kod formatı)

            // SOAP Fault/HTTP hatası kontrolü
            if ($httpCode >= 400 || stripos((string)$responseBody, '<fault') !== false) {
                // Detaylı hata mesajı çıkar
                $faultMsg = 'Unknown SOAP Fault';
                $faultCode = '';
                
                // faultstring'i bul
                if (preg_match('/<faultstring[^>]*>(.*?)<\/faultstring>/is', $responseBody, $matches)) {
                    $faultMsg = trim($matches[1]);
                }
                
                // faultcode'u bul
                if (preg_match('/<faultcode[^>]*>(.*?)<\/faultcode>/is', $responseBody, $matches)) {
                    $faultCode = trim($matches[1]);
                }
                
                // detail içinde daha fazla bilgi varsa al
                $detail = '';
                if (preg_match('/<detail[^>]*>(.*?)<\/detail>/is', $responseBody, $matches)) {
                    $detail = trim(strip_tags($matches[1]));
                }
                
                $errorMessage = "Server Error ($httpCode)";
                if ($faultCode) {
                    $errorMessage .= " [Code: $faultCode]";
                }
                $errorMessage .= ": $faultMsg";
                if ($detail) {
                    $errorMessage .= " | Detail: $detail";
                }
                
                Log::error('[KolaySoft] SOAP Fault Hatası', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'http_code' => $httpCode,
                    'fault_code' => $faultCode,
                    'fault_message' => $faultMsg,
                    'detail' => $detail,
                    'error_message' => $errorMessage,
                    'full_response_body' => $responseBody,
                ]);
                
                return ['success' => false, 'message' => $errorMessage, 'http_code' => $httpCode, 'body' => $responseBody];
            }

            // Yanıtı parse et (örnek kod formatı)
            $code = null;
            $explanation = null;
            $cause = null;
            $documentUUID = null;
            $documentID = null;
            $success = false;
            
            if (is_string($responseBody) && $responseBody !== '') {
                $xml = @simplexml_load_string($responseBody);
                if ($xml) {
                    // Namespace'leri kaydet
                    $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                    $xml->registerXPathNamespace('ns2', $namespace);
                    
                    // XPath ile return node'unu bul
                    $respTag = $action . 'Response';
                    $nodes = $xml->xpath('//soap:Body/ns2:' . $respTag . '/return');
                    
                    // Alternatif: Direkt namespace ile erişim
                    if (empty($nodes)) {
                        $body = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
                        if ($body) {
                            $response = $body->children($namespace)->{$respTag};
                            if ($response && isset($response->return)) {
                                $nodes = [$response->return];
                            }
                        }
                    }
                    
                    // XPath başarılı ise node'dan değerleri al
                    if (!empty($nodes)) {
                        $ret = $nodes[0];
                        $documentUUID = (string)($ret->documentUUID ?? '');
                        $code = (string)($ret->code ?? '');
                        $explanation = (string)($ret->explanation ?? '');
                        $cause = (string)($ret->cause ?? '');
                        $documentID = (string)($ret->documentID ?? '');
                    }
                }
                
                // Her durumda regex fallback kullan (namespace sorunları olabilir)
                if ($code === null || $code === '') {
                    if (preg_match('/<code[^>]*>(.*?)<\/code>/is', $responseBody, $matches)) {
                        $code = trim($matches[1]);
                    }
                }
                if ($explanation === null || $explanation === '') {
                    if (preg_match('/<explanation[^>]*>(.*?)<\/explanation>/is', $responseBody, $matches)) {
                        $explanation = trim($matches[1]);
                    }
                }
                if ($cause === null || $cause === '') {
                    if (preg_match('/<cause[^>]*>(.*?)<\/cause>/is', $responseBody, $matches)) {
                        $cause = trim($matches[1]);
                    }
                }
                if ($documentUUID === null || $documentUUID === '') {
                    if (preg_match('/<documentUUID[^>]*>(.*?)<\/documentUUID>/is', $responseBody, $matches)) {
                        $documentUUID = trim($matches[1]);
                    }
                }
                if ($documentID === null || $documentID === '') {
                    if (preg_match('/<documentID[^>]*>(.*?)<\/documentID>/is', $responseBody, $matches)) {
                        $documentID = trim($matches[1]);
                    }
                }
                
                // Success kontrolü - Sadece code '000' veya '0' ise başarılı
                $success = ($code === '000' || $code === '0');
                
                // Fallback check: code boşsa ve açıklamada başarı ifadesi varsa
                if (!$success && ($code === '' || $code === null)) {
                    $okHints = ['başar', 'success', 'ok', 'olusturuldu', 'oluşturuldu'];
                    $expLower = mb_strtolower((string)$explanation);
                    if ($expLower !== '') {
                        foreach ($okHints as $h) {
                            if (strpos($expLower, $h) !== false) {
                                $success = true;
                                break;
                            }
                        }
                    }
                }
                
                // NOT: documentUUID varlığı başarı göstergesi değil!
                // Code 100, 400, vb. hata kodları başarısızlık demektir.
            }

            if ($success) {
                Log::info('[KolaySoft] Fatura Başarıyla Oluşturuldu', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'document_uuid' => $documentUUID,
                    'invoice_number' => $documentID ?: 'Taslak',
                    'code' => $code,
                    'explanation' => $explanation,
                ]);
                
                return [
                    'success' => true,
                    'http_code' => $httpCode,
                    'code' => $code,
                    'explanation' => $explanation,
                    'cause' => $cause,
                    'documentUUID' => $documentUUID,
                    'invoice_number' => $documentID ?: 'Taslak',
                    'invoice_id' => $documentUUID,
                    'message' => $explanation,
                    'url' => null,
                    'raw' => $responseBody,
                ];
            } else {
                Log::warning('[KolaySoft] Fatura Oluşturulamadı', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'http_code' => $httpCode,
                    'code' => $code,
                    'explanation' => $explanation,
                    'cause' => $cause,
                    'response_body' => $responseBody,
                ]);
                
                $errorMessage = "Invoice Failed";
                if ($code) {
                    $errorMessage .= " (Code: $code)";
                }
                if ($explanation) {
                    $errorMessage .= ": $explanation";
                }
                if ($cause) {
                    $errorMessage .= " - $cause";
                }
                
                return [
                    'success' => false,
                    'http_code' => $httpCode,
                    'code' => $code,
                    'explanation' => $explanation,
                    'cause' => $cause,
                    'message' => $errorMessage,
                    'raw' => $responseBody,
                ];
            }

        } catch (\Exception $e) {
            Log::error('[KolaySoft] Sistem Hatası', [
                'order_id' => $order->id ?? null,
                'order_number' => $order->order_number ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'System Error: ' . $e->getMessage()];
        }
    }
}

