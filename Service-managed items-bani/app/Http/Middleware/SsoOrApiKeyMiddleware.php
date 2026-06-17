<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Role;
use App\Services\IaeCentralService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SsoOrApiKeyMiddleware
{
    protected IaeCentralService $iaeService;

    public function __construct(IaeCentralService $iaeService)
    {
        $this->iaeService = $iaeService;
    }

    public function handle(Request $request, Closure $next, string $ability = 'admin'): Response
    {
        $plainKey = $request->bearerToken() ?: $request->header('X-API-Key');

        if ($plainKey === null || $plainKey === '') {
            return $this->unauthorized('BUTUH AKSES ADMIN');
        }

        // Detect if the key is a JWT (SSO token)
        if (str_contains($plainKey, '.') && count(explode('.', $plainKey)) === 3) {
            $decoded = $this->iaeService->verifyJwt($plainKey);
            if (!$decoded) {
                return $this->unauthorized('Token SSO tidak valid atau sudah kedaluwarsa.');
            }

            // Resolve email from sub or profile
            $email = $decoded->sub ?? $decoded->profile->email ?? null;
            if (!$email) {
                return $this->unauthorized('Token SSO tidak memiliki klaim sub/email.');
            }

            // Check if the user is mapped to a local role
            $localRole = Role::query()->where('email', $email)->first();
            if (!$localRole || $localRole->role !== $ability) {
                return response()->json([
                    'message' => "User {$email} tidak terdaftar sebagai {$ability} lokal.",
                ], 403);
            }

            // Bind values to request attributes for logging / auditing
            $request->attributes->set('auth_type', 'sso');
            $request->attributes->set('sso_email', $email);
            $request->attributes->set('sso_payload', $decoded);

            return $next($request);
        }

        // Fallback to API Key authentication
        $apiKey = ApiKey::query()
            ->where('key_hash', hash('sha256', $plainKey))
            ->first();

        if ($apiKey === null || !$apiKey->can($ability)) {
            return $this->unauthorized('API key tidak valid atau tidak memiliki akses admin.');
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('auth_type', 'api_key');
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
