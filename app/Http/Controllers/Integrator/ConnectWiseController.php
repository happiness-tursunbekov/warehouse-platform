<?php

namespace App\Http\Controllers\Integrator;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

class ConnectWiseController extends Controller
{
    public function productCatalog(Request $request)
    {
        $data = $request->all();

        WebhookLog::create([
            'type' => 'ProductCatalog',
            'data' => $data
        ]);
    }
    public function projects(Request $request)
    {


        WebhookLog::create([
            'type' => 'Projects',
            'data' => $request->all()
        ]);
    }
}
