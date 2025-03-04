<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

class BigCommerceController extends Controller
{
    public function productCreated(Request $request)
    {
        WebhookLog::create([
            'type' => 'store/product/created',
            'data' => $request->all()
        ]);
    }
}
