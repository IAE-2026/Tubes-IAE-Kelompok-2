<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;

class SSOService
{
    private function getBaseUrl(): string
    {
        return rtrim(env('SSO_BASE_URL', 'https://iae-sso.virtualfri.id'), '/');
    }

    public function verifyToken(string $token): object
    {
        $jwksUrl = $this->getBaseUrl() . '/api/v1/auth/jwks';

        // Fetch public keys dari server dosen dengan Caching (1 jam)
        $jwks = Cache::remember('sso_jwks', 3600, function () use ($jwksUrl) {
            $response = Http::get($jwksUrl);

            if ($response->failed()) {
                throw new \Exception('Gagal fetch JWKS dari SSO server');
            }

            return $response->json();
        });

        // Parse JWKS jadi array of Key
        $keys = JWK::parseKeySet($jwks);

        // Decode dan verify JWT
        $decoded = JWT::decode($token, $keys);

        return $decoded;
    }

    public function getMachineToken(): string
    {
        $tokenUrl = $this->getBaseUrl() . '/api/v1/auth/token';
        $apiKey = env('SSO_API_KEY', 'KEY-MHS-203');

        $response = Http::post($tokenUrl, [
            'api_key' => $apiKey,
            'nim' => '102022400161'
        ]);

        if ($response->failed()) {
            throw new \Exception('Gagal ambil M2M token');
        }

        return $response->json('token') ?? $response->json('access_token') ?? '';
    }
}