<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var InventoryMovement $this */
        return [
            'id' => $this->id,
            'sku' => $this->order->inventory->sku,
            'type' => $this->type,
            'direction' => $this->direction,
            'quantity' => $this->quantity,
            'order_id' => $this->order_id,
            'created_at' => $this->created_at,
        ];
    }
}
