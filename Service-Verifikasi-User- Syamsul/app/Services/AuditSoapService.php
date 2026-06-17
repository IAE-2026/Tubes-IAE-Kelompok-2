<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AuditSoapService
{
    /**
     * Send an audit log to the Central SOAP service.
     *
     * @param string $bearerToken
     * @param string $activityName
     * @param array|string $logContent
     * @return string|null ReceiptNumber from the SOAP response
     */
    public function sendAuditLog($bearerToken, $activityName, $logContent)
    {
        $teamId = env('IAE_TEAM_ID', 'TEAM-02'); // Mengambil dari .env
        $jsonContent = is_array($logContent) ? json_encode($logContent) : $logContent;

        $xmlPayload = '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit"><soap:Body><iae:AuditRequest><iae:TeamID>'.$teamId.'</iae:TeamID><iae:ActivityName>'.$activityName.'</iae:ActivityName><iae:LogContent><![CDATA['.$jsonContent.']]></iae:LogContent></iae:AuditRequest></soap:Body></soap:Envelope>';

        try {
            $response = Http::withToken($bearerToken)
                ->withBody($xmlPayload, 'text/xml')
                ->post('https://iae-sso.virtualfri.id/soap/v1/audit');

            if ($response->successful()) {
                $responseBody = $response->body();
                // Extract ReceiptNumber from XML response
                if (preg_match('/<iae:ReceiptNumber>(.*?)<\/iae:ReceiptNumber>/', $responseBody, $matches)) {
                    return $matches[1];
                }
            }
            
            \Log::error('SOAP Audit failed. Response: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            \Log::error('SOAP Audit Exception: ' . $e->getMessage());
            return null;
        }
    }
}
