<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = Order::create([
            'sku' => $request->validated('sku'),
            'qty' => $request->validated('qty'),
            'status' => OrderStatus::Pending,
        ]);

        OrderCreated::dispatch($order);

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return OrderResource::make($order);
    }
}
