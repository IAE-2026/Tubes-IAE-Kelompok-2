<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmqpPublishService
{
    protected SSOService $ssoService;

    public function __construct(SSOService $ssoService)
    {
        $this->ssoService = $ssoService;
    }

    private function getPublishUrl(): string
    {
        $baseUrl = rtrim(env('SSO_BASE_URL', 'https://iae-sso.virtualfri.id'), '/');
        return $baseUrl . '/api/v1/messages/publish';
    }

    /**
     * Mempublikasikan event ke RabbitMQ exchange dosen melalui HTTP publish gateway.
     *
     * @param string $routingKey Routing key untuk event (misalnya: bid.created)
     * @param array $eventData Payload data event
     * @return bool True jika berhasil, False jika gagal
     */
    public function publishEvent(string $routingKey, array $eventData): bool
    {
        try {
            // 1. Ambil token M2M untuk otentikasi Bearer
            $token = $this->ssoService->getMachineToken();

            // 2. Susun parameter request body.
            // Kita mengirimkan payload, message, dan data agar kompatibel dengan schema yang diharapkan di backend API
            $body = [
                'exchange' => 'iae.central.exchange',
                'routing_key' => $routingKey,
                'payload' => $eventData,
                'message' => $eventData,
                'data' => $eventData
            ];

            Log::info("Mengirimkan publish event ke {$this->getPublishUrl()} dengan routing key {$routingKey}");

            // 3. Eksekusi request POST ke Gateway
            $response = Http::withToken($token)
                ->post($this->getPublishUrl(), $body);

            if ($response->failed()) {
                Log::error('Gagal mempublikasikan event AMQP: ' . $response->status() . ' - ' . $response->body());
                return false;
            }

            Log::info('Berhasil mempublikasikan event AMQP: ' . $response->body());
            return true;
        } catch (\Exception $e) {
            Log::error('Exception saat mempublikasikan event AMQP: ' . $e->getMessage());
            return false;
        }
    }
}
