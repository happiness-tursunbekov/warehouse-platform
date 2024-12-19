<?php

namespace App\Http\Controllers\Integrator;

use App\Http\Controllers\Controller;
use App\Models\WebhookTest;
use Illuminate\Http\Request;

class ConnectWiseController extends Controller
{
    public function productCatalog(Request $request)
    {
        WebhookTest::create([
            'type' => 'ProductCatalog',
            'data' => $request->all()
        ]);
    }
}
