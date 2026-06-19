<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoapAuditService
{
    /**
     * Retrieve the M2M token using the configured API Key.
     */
    public function getM2mToken(): string
    {
        return Cache::remember('iae_m2m_token', 3000, function () {
            $baseUrl = config('services.iae.base_url', 'https://iae-sso.virtualfri.id');
            $apiKey = config('services.iae.api_key', 'KEY-MHS-71');

            $response = Http::withoutVerifying()->post($baseUrl . '/api/v1/auth/token', [
                'api_key' => $apiKey,
                'nim' => '102022430010'
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve M2M token: ' . $response->body());
            }

            return $response->json('token');
        });
    }

    /**
     * Send audit log to SOAP endpoint and return ReceiptNumber.
     */
    public function sendAuditLog(string $activityName, array $logContent): ?string
    {
        try {
            $baseUrl = config('services.iae.base_url', 'https://iae-sso.virtualfri.id');
            $teamId = config('services.iae.team_id', 'TEAM-02');
            $token = $this->getM2mToken();

            // Inject student details into the payload as requested
            $logContent['student_name'] = 'Fariz Shadiq';
            $logContent['student_nim'] = '102022430010';

            $jsonLogContent = json_encode($logContent);

            // Construct rigid SOAP Envelope
            $soapEnvelope = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
  <soap:Body>
    <iae:AuditRequest>
      <iae:TeamID>' . e($teamId) . '</iae:TeamID>
      <iae:ActivityName>' . e($activityName) . '</iae:ActivityName>
      <iae:LogContent><![CDATA[' . $jsonLogContent . ']]></iae:LogContent>
    </iae:AuditRequest>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'text/xml; charset=utf-8',
                    'Accept'        => 'application/xml, text/xml, */*',
                ])
                ->withBody($soapEnvelope, 'text/xml')
                ->post($baseUrl . '/soap/v1/audit');

            if ($response->failed()) {
                Log::error('SOAP Audit failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $responseBody = $response->body();

            // Robust parsing of ReceiptNumber using regex to avoid namespace resolution complexities
            if (preg_match('/<iae:ReceiptNumber>(.*?)<\/iae:ReceiptNumber>/s', $responseBody, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match('/<ReceiptNumber>(.*?)<\/ReceiptNumber>/s', $responseBody, $matches)) {
                return trim($matches[1]);
            }

            Log::warning('SOAP Audit response succeeded but ReceiptNumber was not found', [
                'body' => $responseBody
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('Error in SoapAuditService: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return null;
        }
    }
}
