<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type): Response
    {
        if ($type == 'connect-wise') {

            $hashedKey = hash('sha256', config('cw.private_key'), true);

            // Compute HMAC-SHA256 using the hashed key
            $hmac = hash_hmac('sha256', $request->get('Entity'), $hashedKey, true);

            $request->merge([
                'Entity' => json_decode($request->get('Entity'), true),
                'Hash' => base64_encode($hmac)
            ]);
        }

        return $next($request);
    }
}
