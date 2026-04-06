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

it('reserves order immediately when inventory is sufficient', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 10, 'qty_reserved' => 0]);

    $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3])
        ->assertStatus(201);

    $order = Order::first();

    expect($order->status)->toBe(OrderStatus::Reserved);
    expect(InventoryMovement::where('order_id', $order->id)->exists())->toBeTrue();

    $inventory = Inventory::where('sku', 'WIDGET-001')->first();
    expect($inventory->qty_reserved)->toBe(3);
});

it('calls supplier when inventory is insufficient and reserves on ok response', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 0, 'qty_reserved' => 0]);

    Http::fake([
        '*/supplier/reserve' => Http::response(['accepted' => true, 'ref' => 'SUP-TEST-1']),
        '*/supplier/status/SUP-TEST-1' => Http::response(['status' => 'ok']),
    ]);

    Queue::fake([CheckSupplierStatusJob::class]);

    $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3])
        ->assertStatus(201);

    $order = Order::first();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingRestock)
        ->and($order->fresh()->supplier_ref)->toBe('SUP-TEST-1');

    Queue::assertPushed(CheckSupplierStatusJob::class);

    // Manually run the job to simulate queue worker
    (new CheckSupplierStatusJob($order->fresh()))
        ->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Reserved);
    expect(InventoryMovement::where('type', 'supplier_reservation')->exists())->toBeTrue();
});

it('fails order after two delayed responses from supplier', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 0, 'qty_reserved' => 0]);

    Http::fake([
        '*/supplier/reserve' => Http::response(['accepted' => true, 'ref' => 'SUP-DELAY-1']),
        '*/supplier/status/SUP-DELAY-1' => Http::response(['status' => 'delayed']),
    ]);

    Queue::fake([CheckSupplierStatusJob::class]);

    $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3])->assertStatus(201);

    $order = Order::first();

    // First delayed response
    (new CheckSupplierStatusJob($order->fresh()))
        ->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->attempt_count)->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingRestock);

    // Second delayed response - should fail
    (new CheckSupplierStatusJob($order->fresh()))
        ->handle(new SupplierService, new InventoryService);

    expect($order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->fresh()->attempt_count)->toBe(2);
});

it('fails order immediately when supplier declines with accepted false', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 0, 'qty_reserved' => 0]);

    Http::fake([
        '*/supplier/reserve' => Http::response(['accepted' => false, 'ref' => null]),
    ]);

    $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3])->assertStatus(201);

    $order = Order::first();
    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
});

it('fails order when supplier http call throws connection exception', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 0, 'qty_reserved' => 0]);

    Http::fake([
        '*/supplier/reserve' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3])->assertStatus(201);

    $order = Order::first();
    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
});

it('does not create inventory movement when order fails', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 0, 'qty_reserved' => 0]);

    Http::fake([
        '*/supplier/reserve' => Http::response(['accepted' => false, 'ref' => null]),
    ]);

    $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3])->assertStatus(201);

    expect(InventoryMovement::count())->toBe(0);
});

it('correctly updates inventory qty_reserved on successful reservation', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 20, 'qty_reserved' => 5]);

    $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3])->assertStatus(201);

    $inventory = Inventory::where('sku', 'WIDGET-001')->first();
    expect($inventory->qty_reserved)->toBe(8);
});
