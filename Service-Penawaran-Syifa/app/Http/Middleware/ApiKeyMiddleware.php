<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        
        $apiKey = $request->header('x-iae-key');
        
        
        $validKey = env('API_KEY', 'rahasia-bids-123');

        if ($apiKey !== $validKey) {
            return response()->json([
                'message' => 'Unauthorized. API Key tidak valid atau tidak disertakan.'
            ], 401); 
        }

        return $next($request);
    }
}