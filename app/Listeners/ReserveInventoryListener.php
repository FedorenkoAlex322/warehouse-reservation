<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Jobs\CheckSupplierStatusJob;
use App\Services\InventoryService;
use App\Services\SupplierService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReserveInventoryListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly SupplierService $supplierService,
    ) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order->fresh();

        // Guard: skip if order was already processed
        if ($order === null || $order->status !== OrderStatus::Pending) {
            return;
        }

        if ($this->inventoryService->reserve($order)) {
            return;
        }

        // Insufficient stock — call supplier
        try {
            $result = $this->supplierService->reserve(
                sku: $order->sku,
                qty: $order->qty,
            );
        } catch (\Throwable) {
            $order->update(['status' => OrderStatus::Failed]);

            return;
        }

        if (! ($result['accepted'] ?? false)) {
            $order->update(['status' => OrderStatus::Failed]);

            return;
        }

        $order->update([
            'status' => OrderStatus::AwaitingRestock,
            'supplier_ref' => $result['ref'],
        ]);

        CheckSupplierStatusJob::dispatch($order)
            ->delay(now()->addSeconds(15))
            ->onQueue('suppliers');
    }
}
