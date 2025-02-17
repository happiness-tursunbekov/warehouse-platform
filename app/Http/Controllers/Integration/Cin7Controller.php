<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

class Cin7Controller extends Controller
{
    public function saleShipmentAuthorized(Request $request)
    {
        $request->validate([
            'SaleTaskID' => ['required', 'string'],
            'OrderNumber' => ['required', 'string'],
            'EventType' => ['required', 'string']
        ]);

        WebhookLog::create([
            'type' => $request->get('EventType'),
            'data' => $request->all()
        ]);
    }
}
