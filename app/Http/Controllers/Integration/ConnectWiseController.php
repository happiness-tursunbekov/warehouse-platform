<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

class ConnectWiseController extends Controller
{
    public function productCatalog(Request $request)
    {
        WebhookLog::create([
            'type' => 'ProductCatalog',
            'data' => $request->all()
        ]);
    }

    public function project(Request $request)
    {
        WebhookLog::create([
            'type' => 'Project',
            'data' => $request->all()
        ]);
    }

    public function activity(Request $request)
    {
        WebhookLog::create([
            'type' => 'Activity',
            'data' => $request->all()
        ]);
    }
}
