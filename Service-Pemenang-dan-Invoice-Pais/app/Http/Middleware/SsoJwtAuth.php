<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SsoJwtAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorizationHeader = $request->header('Authorization');

        if (empty($authorizationHeader) || !Str::startsWith($authorizationHeader, 'Bearer ')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Bearer token is missing.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = Str::substr($authorizationHeader, 7);

        try {
            // Fetch and cache the JWKS from SSO server (cached for 24 hours)
            $jwks = Cache::remember('sso_jwks', 86400, function () {
                $response = Http::withoutVerifying()->get('https://iae-sso.virtualfri.id/api/v1/auth/jwks');
                if ($response->failed()) {
                    throw new \Exception('Failed to fetch JWKS from SSO server.');
                }
                return $response->json();
            });

            // Parse JWKS keys using firebase/php-jwt
            $keys = JWK::parseKeySet($jwks);

            // Decode token using RS256 keys
            $decoded = JWT::decode($token, $keys);
            $payload = (array) $decoded;

            $user = null;

            if (isset($payload['token_type']) && $payload['token_type'] === 'user') {
                $profile = (array) ($payload['profile'] ?? []);
                $email = $profile['email'] ?? $payload['sub'] ?? null;
                $name = $profile['name'] ?? 'SSO User';

                if (!$email) {
                    throw new \Exception('Email claim is missing in JWT payload.');
                }

                // Map Warga role dynamically based on domain or email format
                $roleName = 'Warga';
                if (Str::endsWith($email, '@ktp.iae.id')) {
                    $roleName = 'Warga';
                }

                $role = Role::firstOrCreate(['name' => $roleName]);

                $user = User::where('email', $email)->first();
                if (!$user) {
                    $user = User::create([
                        'name'     => $name,
                        'email'    => $email,
                        'password' => bcrypt(Str::random(16)),
                        'role_id'  => $role->id,
                    ]);
                } else if (!$user->role_id) {
                    $user->update(['role_id' => $role->id]);
                }
            } else if (isset($payload['token_type']) && $payload['token_type'] === 'm2m') {
                $app = (array) ($payload['app'] ?? []);
                $clientId = $app['client_id'] ?? $payload['sub'] ?? 'm2m';
                $name = $app['name'] ?? 'M2M App';
                $email = $clientId . '@m2m.local';

                $role = Role::firstOrCreate(['name' => 'Admin']);

                $user = User::where('email', $email)->first();
                if (!$user) {
                    $user = User::create([
                        'name'     => $name,
                        'email'    => $email,
                        'password' => bcrypt(Str::random(16)),
                        'role_id'  => $role->id,
                    ]);
                } else if (!$user->role_id) {
                    $user->update(['role_id' => $role->id]);
                }
            } else {
                throw new \Exception('Unsupported token type.');
            }

            // Authenticate the user for the current request
            Auth::login($user);

            // Keep the decoded token in the request properties for downstream components
            $request->attributes->set('sso_user', $user);
            $request->attributes->set('sso_payload', $payload);

            return $next($request);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: ' . $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
