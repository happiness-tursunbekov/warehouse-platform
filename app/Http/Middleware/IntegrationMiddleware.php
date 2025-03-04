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
        switch ($type) {
            case 'connect-wise':

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

                $cache = cache()->get('cw-webhook-' . $request->get('Type') . $request->get('ID'));

                if ($cache) {
                    return \response()->json(['Message' => 'Successful!']);
                }

                if (config('cw.access_key') != $request->get('key')) {
                    return \response()->json(['key' => 'Incorrect Key'], 422);
                }

                $request->merge([
                    'Entity' => json_decode($request->get('Entity'), true),
                    'key' => ''
                ]);

                cache()->put('cw-webhook-' . $request->get('Type') . $request->get('ID'), true, now()->addSeconds(5));

                break;

            case 'cin7':

                $request->validate([
                    'key' => ['required', 'string'],
                ]);

                if (config('cin7.access_key') != $request->get('key')) {
                    return \response()->json(['key' => 'Incorrect Key'], 422);
                }

                $request->merge([
                    'key' => ''
                ]);

                break;

            case 'big-commerce':

                $request->validate([
                    'key' => ['required', 'string'],
                ]);

                if (config('bc.access_key') != $request->get('key')) {
                    return \response()->json(['key' => 'Incorrect Key'], 422);
                }

                $request->merge([
                    'key' => ''
                ]);

                break;
            default:
                abort(404);
        }

        return $next($request);
    }
}
