<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmqpPublisherService
{
    /**
     * Retrieve the M2M token.
     */
    protected function getM2mToken(): string
    {
        return Cache::remember('iae_m2m_token', 3000, function () {
            $baseUrl = config('services.iae.base_url', 'https://iae-sso.virtualfri.id');
            $apiKey = config('services.iae.api_key', 'KEY-MHS-71');

            $response = Http::withoutVerifying()->post($baseUrl . '/api/v1/auth/token', [
                'api_key' => $apiKey,
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve M2M token: ' . $response->body());
            }

            return $response->json('token');
        });
    }

    /**
     * Publish an event to the centralized exchange.
     */
    public function publishEvent(string $routingKey, array $messagePayload): bool
    {
        try {
            $baseUrl = config('services.iae.base_url', 'https://iae-sso.virtualfri.id');
            $token = $this->getM2mToken();

            // Inject student details into the message payload
            $messagePayload['student_name'] = 'Fariz Shadiq';
            $messagePayload['student_nim'] = '102022430010';

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ])
                ->post($baseUrl . '/api/v1/messages/publish', [
                    'routing_key' => $routingKey,
                    'message'     => $messagePayload,
                ]);

            if ($response->failed()) {
                Log::error('AMQP Publish failed via HTTP gateway', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error in AmqpPublisherService: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return false;
        }
    }
}
