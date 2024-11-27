<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\ConnectWiseService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(ConnectWiseService $connectWiseService)
    {
        $projects = $connectWiseService->getProjects(null, 'closedFlag=false');
        $teams = $connectWiseService->getSystemDepartments();

        return response()->json([
            'projects' => $projects,
            'teams' => $teams
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'projectId' => ['required', 'integer'],
            'teamId' => ['required', 'integer'],
            'totalCost' => ['required', 'numeric'],
            'items' => ['required', 'array'],
            'items.*.productId' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer'],
            'items.*.cost' => ['required', 'numeric']
        ]);

        $order = new Order([
            'projectId' => $request->get('projectId'),
            'teamId' => $request->get('teamId'),
            'totalCost' => $request->get('totalCost'),
            'authorType' => User::class,
            'authorId' => $request->user()->id,
        ]);
        $order->save();

        array_map(function ($itemData) use ($order) {
            $item = new OrderItem($itemData);
            $item->setAttribute('orderId', $order->id);
            $item->save();
        }, $request->get('items'));

        return response()->json($order);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
