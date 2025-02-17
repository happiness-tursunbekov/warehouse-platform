<?php

namespace App\Http\Middleware;

use App\Services\ConnectWiseService;
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

            $actions = [
                ConnectWiseService::ACTION_ADDED,
                ConnectWiseService::ACTION_UPDATED,
                ConnectWiseService::ACTION_DELETED
            ];

            $request->validate([
                'key' => ['required', 'string'],
                'ID' => ['required', 'integer'],
                'Action' => ['required', 'string', "in:" . implode(',', $actions)],
                'Type' => ['required', 'string'],
                'Entity' => ['nullable', 'required_unless:Action,' . ConnectWiseService::ACTION_DELETED, 'string']
            ]);

            if (config('cw.access_key') != $request->get('key')) {
                return \response()->json(['key' => 'Incorrect Key'], 422);
            }

            $request->merge([
                'Entity' => json_decode($request->get('Entity'), true),
                'key' => ''
            ]);
        }

        elseif ($type == 'cin7') {

            $request->validate([
                'key' => ['required', 'string'],
            ]);

            if (config('cin7.access_key') != $request->get('key')) {
                return \response()->json(['key' => 'Incorrect Key'], 422);
            }

            $request->merge([
                'key' => ''
            ]);
        }

        return $next($request);
    }
}
