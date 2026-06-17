<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MessagePublisherService
{
    /**
     * Publish a message to the Central Message Broker (RabbitMQ) via HTTP API.
     *
     * @param string $bearerToken
     * @param string $eventName
     * @param array $payload
     * @return bool
     */
    public function publishMessage($bearerToken, $eventName, $payload)
    {
        try {
            $body = array_merge([
                'event_name' => $eventName,
                'service_name' => 'Verifikasi-Service',
                'api_version' => 'v1',
                'occurred_at' => now()->toIso8601String(),
            ], $payload);

            $response = Http::withToken($bearerToken)
                ->post('https://iae-sso.virtualfri.id/api/v1/messages/publish', [
                    'routing_key' => 'verification.created',
                    'message' => $body
                ]);

            if ($response->successful()) {
                return true;
            }

            \Log::error('AMQP Publish failed. Response: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            \Log::error('AMQP Publish Exception: ' . $e->getMessage());
            return false;
        }
    }
}
