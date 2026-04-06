<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\InventoryMovementResource;
use App\Models\InventoryMovement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function movements(string $sku): AnonymousResourceCollection
    {
        return InventoryMovementResource::collection(
            InventoryMovement::where('sku', $sku)->orderBy('created_at', 'desc')->get()
        );
    }
}
