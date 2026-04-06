<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Order $this */
        return [
            'id' => $this->id,
            'sku' => $this->inventory->sku,
            'qty' => $this->qty,
            'status' => $this->status->value,
            'supplier_ref' => $this->supplier_ref,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
