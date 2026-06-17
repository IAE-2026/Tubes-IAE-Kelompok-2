<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ApiKeyAuth
 *
 * Memvalidasi X-IAE-KEY header pada setiap request.
 * API Key disimpan di .env sebagai IAE_API_KEY.
 *
 * Cara penggunaan di Postman:
 *   Header Key  : X-IAE-KEY
 *   Header Value: YOUR_NIM (contoh: 2021XXXXXXX)
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey         = $request->header('X-IAE-KEY');
        $validApiKey    = config('app.iae_api_key');

        // Cek apakah header ada
        if (empty($apiKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API Key tidak ditemukan. Sertakan header X-IAE-KEY.',
                'errors'  => [
                    'header' => 'X-IAE-KEY header is required.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validasi API Key
        if ($apiKey !== $validApiKey) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API Key tidak valid atau tidak memiliki akses.',
                'errors'  => [
                    'header' => 'Invalid X-IAE-KEY value.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
