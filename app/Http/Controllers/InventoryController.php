<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\InventoryMovementResource;
use App\Models\Inventory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function movements(string $sku): AnonymousResourceCollection
    {
        $inventory = Inventory::where('sku', $sku)->firstOrFail();

        $movements = $inventory->movements()
            ->with('order.inventory')
            ->orderByDesc('inventory_movements.created_at')
            ->get();

        return InventoryMovementResource::collection($movements);
    }
}
