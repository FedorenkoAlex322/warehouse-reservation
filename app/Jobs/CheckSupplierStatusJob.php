<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\SupplierStatus;
use App\Models\Order;
use App\Services\InventoryService;
use App\Services\SupplierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckSupplierStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        private readonly Order $order,
    ) {
        $this->onQueue('suppliers');
    }

    public function handle(SupplierService $supplierService, InventoryService $inventoryService): void
    {
        $order = $this->order->fresh();

        // Guard: skip if order is no longer awaiting restock
        if ($order === null || $order->status !== OrderStatus::AwaitingRestock) {
            return;
        }

        try {
            $rawStatus = $supplierService->checkStatus((string) $order->supplier_ref);
        } catch (\Throwable) {
            $order->update(['status' => OrderStatus::Failed]);

            return;
        }

        $status = SupplierStatus::tryFrom($rawStatus);

        match ($status) {
            SupplierStatus::Ok => $inventoryService->forceReserve($order),
            SupplierStatus::Fail => $order->update(['status' => OrderStatus::Failed]),
            SupplierStatus::Delayed => $this->handleDelayed($order),
            default => $order->update(['status' => OrderStatus::Failed]),
        };
    }

    private function handleDelayed(Order $order): void
    {
        $order->increment('attempt_count');
        $order->refresh();

        if ($order->attempt_count >= 2) {
            $order->update(['status' => OrderStatus::Failed]);

            return;
        }

        self::dispatch($order)
            ->delay(now()->addSeconds(15))
            ->onQueue('suppliers');
    }
}
