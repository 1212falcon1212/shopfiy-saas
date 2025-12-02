<?php

namespace App\Support\Integration\Invoice\EfaturaAi;

use App\Models\InvoiceIntegration;
use App\Support\Integration\Invoice\Contracts\InvoiceProviderInterface;
use Illuminate\Support\Facades\Log;

class EfaturaAi implements InvoiceProviderInterface
{
    protected ?string $username = null;
    protected ?string $password = null;
    protected ?string $sourceUrn = null; // e-Fatura etiketi
    protected array $config;

    public function __construct()
    {
        $this->config = config('efatura_ai');
    }

    public function initForCustomer(InvoiceIntegration $integration): self
    {
        $credentials = $integration->credentials ?? [];
        $this->username = $credentials['username'] ?? $this->config['username'];
        $this->password = $credentials['password'] ?? $this->config['password'];
        $this->sourceUrn = $credentials['source_urn'] ?? null;
        return $this;
    }

    public function sendEArchiveInvoice(array $payload): array
    {
        $action = 'sendInvoice';
        $service = 'EArchiveInvoiceService';
        $serviceWs = 'EArchiveInvoiceWS';

        $document = $payload; // Payload zaten doğru yapıda geliyor.
        $xmlContent = $document['xml'] ?? '';

        // UBL XML temizliği: BOM, XML deklarasyonu ve baştaki boşlukları kaldır
        if ($xmlContent !== '') {
            // UTF-8 BOM
            $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);
            // XML deklarasyonu
            $xmlContent = preg_replace('/^<\?xml[^>]*?\?>/s', '', $xmlContent);
            // Baştaki boşluklar/yeni satırlar
            $xmlContent = ltrim($xmlContent);
        }

        // e-Arşiv: doğrudan invoiceXMLList bekliyor, source/dest yok
        $innerXml = '<invoiceXMLList>';
        $innerXml .= '<xmlContent><![CDATA[' . $xmlContent . ']]></xmlContent>';
        if (isset($document['documentUUID'])) {
            $innerXml .= '<documentUUID>' . htmlspecialchars($document['documentUUID'], ENT_XML1) . '</documentUUID>';
        }
        if (isset($document['documentId'])) {
            $innerXml .= '<documentId>' . htmlspecialchars($document['documentId'], ENT_XML1) . '</documentId>';
        }
        if (isset($document['documentDate'])) {
            $innerXml .= '<documentDate>' . htmlspecialchars($document['documentDate'], ENT_XML1) . '</documentDate>';
        }
        if (isset($document['note'])) {
            $innerXml .= '<note>' . htmlspecialchars($document['note'], ENT_XML1) . '</note>';
        }
        $innerXml .= '</invoiceXMLList>';
        
        return $this->requestSoap($action, $innerXml, $service, $serviceWs);
    }

    public function sendEInvoice(array $payload): array
    {
        $action = 'sendInvoice';
        $service = 'InvoiceService';
        $serviceWs = 'InvoiceWS';

        $document = $payload; // ['xml','documentUUID','documentId','documentDate','destinationUrn']
        $xmlContent = $document['xml'] ?? '';

        if ($xmlContent !== '') {
            $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);
            $xmlContent = preg_replace('/^<\?xml[^>]*?\?>/s', '', $xmlContent);
            $xmlContent = ltrim($xmlContent);
        }

        $sourceUrn = (string)($document['sourceUrn'] ?? $this->sourceUrn ?? '');
        $destinationUrn = (string)($document['destinationUrn'] ?? '');

        $innerXml = '<inputDocumentList>';
        $innerXml .= '<inputDocument>';
        if ($sourceUrn !== '') {
            $innerXml .= '<sourceUrn>' . htmlspecialchars($sourceUrn, ENT_XML1) . '</sourceUrn>';
        }
        if ($destinationUrn !== '') {
            $innerXml .= '<destinationUrn>' . htmlspecialchars($destinationUrn, ENT_XML1) . '</destinationUrn>';
        }
        if (isset($document['documentUUID'])) {
            $innerXml .= '<documentUUID>' . htmlspecialchars($document['documentUUID'], ENT_XML1) . '</documentUUID>';
        }
        if (isset($document['documentId'])) {
            $innerXml .= '<documentId>' . htmlspecialchars($document['documentId'], ENT_XML1) . '</documentId>';
        }
        if (isset($document['documentDate'])) {
            $innerXml .= '<documentDate>' . htmlspecialchars($document['documentDate'], ENT_XML1) . '</documentDate>';
        }
        $innerXml .= '<xmlContent><![CDATA[' . $xmlContent . ']]></xmlContent>';
        $innerXml .= '</inputDocument>';
        $innerXml .= '</inputDocumentList>';

        return $this->requestSoap($action, $innerXml, $service, $serviceWs);
    }

    protected function requestSoap(string $action, string $innerXml, string $service, string $serviceWs): array
    {
        $serviceUrl = $this->config['base_url'] . '/' . $service . '/' . $serviceWs;
        $namespace = 'http://' . strtolower($service) . '.entegrator.com/';

        $envelope = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ear="' . $namespace . '">';
        $envelope .= '<soapenv:Header/>';
        $envelope .= '<soapenv:Body>';
        $envelope .= '<ear:' . $action . '>';
        $envelope .= $innerXml;
        $envelope .= '</ear:' . $action . '>';
        $envelope .= '</soapenv:Body>';
        $envelope .= '</soapenv:Envelope>';

        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'Accept: text/xml',
            'Username: ' . ($this->username ?? ''),
            'Password: ' . ($this->password ?? ''),
            'SOAPAction: ""',
        ];

        if ($this->config['debug']) {
            Log::info('[EfaturaAI][Request]', [
                'url' => $serviceUrl,
                'headers' => ['Username' => $this->username ? '***' : null],
                'action' => $action,
                'inner_xml_preview' => substr($innerXml, 0, 1024),
            ]);
        }

        $ch = curl_init($serviceUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $envelope);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)($this->config['timeout'] ?? 10));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)($this->config['timeout'] ?? 10));

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($this->config['debug']) {
            Log::info('[EfaturaAI][Response]', [
                'http_code' => $httpCode,
                'body_preview' => substr((string)$responseBody, 0, 2048)
            ]);
        }

        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: ' . $error, 'http_code' => $httpCode];
        }

        // SOAP Fault/HTTP hatası
        if ($httpCode >= 400 || stripos((string)$responseBody, '<fault') !== false) {
            return ['success' => false, 'message' => 'SOAP Fault or HTTP Error', 'http_code' => $httpCode, 'body' => $responseBody];
        }

        // Yanıtı parse ederek code/explanation/cause çıkar
        $code = null; $explanation = null; $cause = null; $documentUUID = null;
        $success = false;
        if (is_string($responseBody) && $responseBody !== '') {
            $xml = @simplexml_load_string($responseBody);
            if ($xml) {
                $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                $xml->registerXPathNamespace('ns2', $namespace);
                $respTag = $action . 'Response';
                $node = $xml->xpath('//ns2:' . $respTag . '/return');
                if (!empty($node)) {
                    $ret = $node[0];
                    $documentUUID = (string)($ret->documentUUID ?? '');
                    $code = (string)($ret->code ?? '');
                    $explanation = (string)($ret->explanation ?? '');
                    $cause = (string)($ret->cause ?? '');
                    $success = ($code === '000' || $code === '0');
                    $okHints = ['başar', 'success', 'ok', 'olusturuldu', 'oluşturuldu'];
                    $expLower = mb_strtolower((string)$explanation);
                    if (!$success && $code === '' && $expLower !== '') {
                        foreach ($okHints as $h) { if (strpos($expLower, $h) !== false) { $success = true; break; } }
                    }
                    if (!$success && $documentUUID) { $success = true; }
                }
            }
        }

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'code' => $code,
            'explanation' => $explanation,
            'cause' => $cause,
            'documentUUID' => $documentUUID,
            'raw' => $responseBody,
        ];
    }

    public function updateEArchiveInvoice(array $payload): array
    {
        return ['success' => false, 'message' => 'Not implemented yet.'];
    }

    public function cancelInvoice(string $invoiceUuid, array $options = []): array
    {
        $action = 'cancelInvoice';
        $service = 'EArchiveInvoiceService';
        $serviceWs = 'EArchiveInvoiceWS';

        $cancelReason = (string)($options['cancelReason'] ?? 'Sipariş İptali');
        $cancelDate = (string)($options['cancelDate'] ?? date('Y-m-d'));

        $innerXml = '';
        $innerXml .= '<invoiceUuid>' . htmlspecialchars($invoiceUuid, ENT_XML1) . '</invoiceUuid>';
        $innerXml .= '<cancelReason>' . htmlspecialchars($cancelReason, ENT_XML1) . '</cancelReason>';
        $innerXml .= '<cancelDate>' . htmlspecialchars($cancelDate, ENT_XML1) . '</cancelDate>';

        return $this->requestSoap($action, $innerXml, $service, $serviceWs);
    }
    
    public function queryInvoice(string $paramType, string $parameter, string $withXML = 'NO'): array
    {
        return ['success' => false, 'message' => 'Not implemented yet.'];
    }

    public function setEmailSent(array $invoiceUuidList): array
    {
        return ['success' => false, 'message' => 'Not implemented yet.'];
    }

    public function getPdf(string $documentId): ?string
    {
        return null;
    }

    public function check(): array
    {
        return ['success' => true];
    }
}


