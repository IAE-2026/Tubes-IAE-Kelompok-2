<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SSOService;
use App\Models\User;

class SSOAuthMiddleware
{
    protected SSOService $ssoService;

    public function __construct(SSOService $ssoService)
    {
        $this->ssoService = $ssoService;
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Token tidak ditemukan. Sertakan Authorization: Bearer <token>'
            ], 401);
        }

        try {
            $payload = $this->ssoService->verifyToken($token);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Token tidak valid',
                'detail' => $e->getMessage()
            ], 401);
        }

        $email = $payload->email ?? $payload->profile->email ?? $payload->sub;
        $name = $payload->name ?? $payload->profile->name ?? 'SSO User';

        $role = $payload->role ?? $payload->profile->role ?? null;
        if (!$role) {
            if (str_contains($email, 'warga') || str_contains($email, 'ktp.iae.id')) {
                $role = 'bidder';
            } else {
                $role = 'viewer';
            }
        }

        $user = User::firstOrCreate(
            ['sso_id' => $payload->sub],
            [
                'name'     => $name,
                'email'    => $email,
                'password' => bcrypt('sso-managed'),
                'sso_role' => $role,
            ]
        );

        $user->update(['sso_role' => $role]);

        $request->merge(['sso_user' => $user]);
        $request->setUserResolver(fn() => $user);

        return $next($request);
    }
}