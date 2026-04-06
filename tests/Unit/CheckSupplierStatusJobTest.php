<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Jobs\CheckSupplierStatusJob;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Services\InventoryService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeAwaitingOrder(int $attemptCount = 0): Order
{
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 0, 'qty_reserved' => 0]);

    return Order::create([
        'inventory_id' => $inventory->id,
        'qty' => 3,
        'status' => OrderStatus::AwaitingRestock,
        'supplier_ref' => 'SUP-123',
        'attempt_count' => $attemptCount,
    ]);
}

it('sets order to reserved when supplier returns ok', function (): void {
    $order = makeAwaitingOrder();

    Http::fake(['*/supplier/status/*' => Http::response(['status' => 'ok'])]);

    (new CheckSupplierStatusJob($order))->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Reserved);
    expect(InventoryMovement::count())->toBe(1);
});

it('sets order to failed when supplier returns fail', function (): void {
    $order = makeAwaitingOrder();

    Http::fake(['*/supplier/status/*' => Http::response(['status' => 'fail'])]);

    (new CheckSupplierStatusJob($order))->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
});

it('increments attempt count and redispatches on first delayed response', function (): void {
    Queue::fake();
    $order = makeAwaitingOrder();

    Http::fake(['*/supplier/status/*' => Http::response(['status' => 'delayed'])]);

    (new CheckSupplierStatusJob($order))->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->attempt_count)->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingRestock);

    Queue::assertPushed(CheckSupplierStatusJob::class);
});

it('sets order to failed after second delayed response', function (): void {
    Queue::fake();
    $order = makeAwaitingOrder(attemptCount: 1);

    Http::fake(['*/supplier/status/*' => Http::response(['status' => 'delayed'])]);

    (new CheckSupplierStatusJob($order))->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
    Queue::assertNotPushed(CheckSupplierStatusJob::class);
});

it('skips processing if order is already in reserved status', function (): void {
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 10, 'qty_reserved' => 0]);
    $order = Order::create([
        'inventory_id' => $inventory->id,
        'qty' => 3,
        'status' => OrderStatus::Reserved,
    ]);

    Http::fake();

    (new CheckSupplierStatusJob($order))->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Reserved);
    Http::assertNothingSent();
});

it('skips processing if order is already in failed status', function (): void {
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 10, 'qty_reserved' => 0]);
    $order = Order::create([
        'inventory_id' => $inventory->id,
        'qty' => 3,
        'status' => OrderStatus::Failed,
    ]);

    Http::fake();

    (new CheckSupplierStatusJob($order))->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
    Http::assertNothingSent();
});

it('sets order to failed when supplier http call throws', function (): void {
    $order = makeAwaitingOrder();

    Http::fake([
        '*/supplier/status/*' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    (new CheckSupplierStatusJob($order))->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
});

it('is configured for suppliers queue with 1 try', function (): void {
    $order = makeAwaitingOrder();
    $job = new CheckSupplierStatusJob($order);

    expect($job->tries)->toBe(1)
        ->and($job->queue)->toBe('suppliers');
});
