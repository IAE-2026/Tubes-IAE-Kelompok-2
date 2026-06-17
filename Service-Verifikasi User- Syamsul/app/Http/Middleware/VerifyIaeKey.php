<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Role;

class VerifyIaeKey
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Get Authorization Header
        $header = $request->header('Authorization');
        if (!$header || !preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            // Coba cek header lama jika dosen masih testing X-IAE-KEY
            $headerkey = $request->header('X-IAE-KEY');
            if ($headerkey === '102022400117') {
                return $next($request);
            }
            return $this->errorResponse('Unauthorized. Missing or invalid Bearer token.', 401);
        }

        $token = $matches[1];

        try {
            // 2. Fetch JWKS and Cache it
            $jwks = Cache::remember('sso_jwks', 3600, function () {
                $jwksUrl = env('SSO_JWKS_URL', 'https://iae-sso.virtualfri.id/api/v1/auth/jwks');
                $response = Http::timeout(5)->get($jwksUrl);
                if ($response->successful()) {
                    return $response->json();
                }
                throw new \Exception('Failed to fetch JWKS from SSO server.');
            });

            // 3. Decode JWT Token
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);

            // 4. Map user to roles table
            $email = $decoded->email ?? ($decoded->sub ?? 'unknown');
            $role_name = $decoded->role ?? 'user';
            
            Role::updateOrCreate(
                ['email' => $email],
                ['role_name' => $role_name]
            );

            // Set user payload into request for controller usage
            $request->attributes->set('user_email', $email);

        } catch (\Exception $e) {
            return $this->errorResponse('Unauthorized. Token validation failed: ' . $e->getMessage(), 401);
        }

        return $next($request);
    }
}
