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

            if (config('cw.access_key') != $request->get('key')) {
                return \response()->json(['key' => 'Incorrect Key'], 422);
            }

            $request->merge([
                'Entity' => json_decode($request->get('Entity'), true),
                'key' => ''
            ]);
        }

        return $next($request);
    }
}
