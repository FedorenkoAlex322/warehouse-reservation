<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Attempt to reserve inventory for the order.
     * Returns true if reservation succeeded (sufficient stock).
     * Returns false if insufficient stock — caller must handle supplier fallback.
     */
    public function reserve(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $inventory = Inventory::where('sku', $order->sku)
                ->lockForUpdate()
                ->first();

            if ($inventory === null || $inventory->availableQty() < $order->qty) {
                return false;
            }

            $inventory->qty_reserved += $order->qty;
            $inventory->save();

            InventoryMovement::create([
                'sku' => $order->sku,
                'qty_change' => -$order->qty,
                'type' => MovementType::Reservation->value,
                'order_id' => $order->id,
            ]);

            $order->update(['status' => OrderStatus::Reserved]);

            return true;
        });
    }

    /**
     * Force-reserve inventory when supplier confirms delivery ("ok").
     * Does not check available qty — supplier has already confirmed stock.
     */
    public function forceReserve(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $inventory = Inventory::where('sku', $order->sku)
                ->lockForUpdate()
                ->first();

            if ($inventory === null) {
                Inventory::create([
                    'sku' => $order->sku,
                    'qty_total' => $order->qty,
                    'qty_reserved' => $order->qty,
                ]);
            } else {
                $inventory->qty_total += $order->qty;
                $inventory->qty_reserved += $order->qty;
                $inventory->save();
            }

            InventoryMovement::create([
                'sku' => $order->sku,
                'qty_change' => -$order->qty,
                'type' => MovementType::SupplierReservation->value,
                'order_id' => $order->id,
            ]);

            $order->update(['status' => OrderStatus::Reserved]);
        });
    }
}
