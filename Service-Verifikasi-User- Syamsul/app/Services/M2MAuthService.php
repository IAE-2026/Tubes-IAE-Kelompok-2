<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class M2MAuthService
{
    /**
     * Get a valid M2M token from the Central SSO.
     * Uses caching to avoid requesting a new token on every request.
     *
     * @return string|null
     */
    public function getToken()
    {
        // Cache the token for 55 minutes (usually tokens expire in 1 hour)
        return Cache::remember('m2m_token', 3300, function () {
            $apiKey = env('IAE_API_KEY', 'KEY-MHS-158');
            
            $response = Http::post('https://iae-sso.virtualfri.id/api/v1/auth/token', [
                'api_key' => $apiKey,
                'nim' => '102022400117'
            ]);
            
            if ($response->successful()) {
                // Asumsi response berbentuk {"token": "eyJhbG..."} atau {"access_token": "..."}
                $data = $response->json();
                return $data['token'] ?? $data['access_token'] ?? null;
            }

            \Log::error('Failed to fetch M2M Token. Response: ' . $response->body());
            throw new \Exception('Failed to fetch M2M Token from SSO.');
        });
    }
}
