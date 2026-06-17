<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoapAuditService
{
    protected SSOService $ssoService;

    public function __construct(SSOService $ssoService)
    {
        $this->ssoService = $ssoService;
    }

    private function getSoapUrl(): string
    {
        $baseUrl = rtrim(env('SSO_BASE_URL', 'https://iae-sso.virtualfri.id'), '/');
        return $baseUrl . '/soap/v1/audit';
    }

    /**
     * Mengirim log aktivitas ke SOAP Audit Server Dosen.
     *
     * @param string $activityName Nama aktivitas bisnis (misalnya: CreateBid)
     * @param array $logData Data transaksi penawaran
     * @return string|null Mengembalikan ReceiptNumber jika sukses, null jika gagal
     */
    public function logActivity(string $activityName, array $logData): ?string
    {
        try {
            // 1. Dapatkan token M2M untuk otentikasi Bearer
            $token = $this->ssoService->getMachineToken();

            // 2. Format data JSON ke string untuk dimasukkan ke CDATA
            $jsonData = json_encode($logData);

            $teamId = env('TEAM_ID', 'TEAM-02');

            // 3. Susun XML Envelope kaku sesuai spesifikasi SOAP Audit Dosen
            $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
    <soap:Body>
        <iae:AuditRequest>
            <iae:TeamID>{$teamId}</iae:TeamID>
            <iae:ActivityName>{$activityName}</iae:ActivityName>
            <iae:LogContent><![CDATA[{$jsonData}]]></iae:LogContent>
        </iae:AuditRequest>
    </soap:Body>
</soap:Envelope>
XML;

            Log::info("Mengirim SOAP request ke {$this->getSoapUrl()}");

            // 4. Kirim SOAP request
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ])->withBody($xmlPayload, 'text/xml')
              ->post($this->getSoapUrl());

            if ($response->failed()) {
                Log::error('SOAP Audit Request failed: ' . $response->status() . ' - ' . $response->body());
                return null;
            }

            $responseBody = $response->body();
            Log::info('SOAP Response received: ' . $responseBody);

            // 5. Ekstrak ReceiptNumber menggunakan regular expression (sangat aman dari perbedaan namespace prefix)
            if (preg_match('/<(?:iae:)?ReceiptNumber>(.*?)<\/(?:iae:)?ReceiptNumber>/i', $responseBody, $matches)) {
                $receiptNumber = trim($matches[1]);
                Log::info("Berhasil mengekstrak ReceiptNumber: {$receiptNumber}");
                return $receiptNumber;
            }

            // Fallback parsing menggunakan SimpleXML
            $cleanXml = preg_replace('/<(?:soap|iae):/', '<', $responseBody);
            $cleanXml = preg_replace('/<\/(?:soap|iae):/', '</', $cleanXml);
            $xml = simplexml_load_string($cleanXml);
            if ($xml && isset($xml->Body->AuditResponse->ReceiptNumber)) {
                $receiptNumber = (string) $xml->Body->AuditResponse->ReceiptNumber;
                Log::info("Fallback parsing berhasil mengekstrak ReceiptNumber: {$receiptNumber}");
                return $receiptNumber;
            }

            Log::warning('Gagal menemukan ReceiptNumber pada respon SOAP');
            return null;
        } catch (\Exception $e) {
            Log::error('SOAP Audit Exception: ' . $e->getMessage());
            return null;
        }
    }
}
