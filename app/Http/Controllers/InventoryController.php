<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\InventoryMovementResource;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function movements(string $sku): AnonymousResourceCollection
    {
        $inventory = Inventory::where('sku', $sku)->firstOrFail();

        $movements = InventoryMovement::query()
            ->join('orders', 'inventory_movements.order_id', '=', 'orders.id')
            ->where('orders.inventory_id', $inventory->id)
            ->select('inventory_movements.*')
            ->with('order.inventory')
            ->orderBy('inventory_movements.created_at', 'desc')
            ->get();

        return InventoryMovementResource::collection($movements);
    }
}
