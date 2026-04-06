<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new InventoryService;
});

it('reserves inventory when sufficient stock is available', function (): void {
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 10, 'qty_reserved' => 0]);
    $order = Order::create(['inventory_id' => $inventory->id, 'qty' => 3, 'status' => OrderStatus::Pending]);

    $result = $this->service->reserve($order);

    expect($result)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Reserved)
        ->and($inventory->fresh()->qty_reserved)->toBe(3);

    expect(InventoryMovement::where('order_id', $order->id)->exists())->toBeTrue();
});

it('returns false when stock is insufficient', function (): void {
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 2, 'qty_reserved' => 0]);
    $order = Order::create(['inventory_id' => $inventory->id, 'qty' => 5, 'status' => OrderStatus::Pending]);

    $result = $this->service->reserve($order);

    expect($result)->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);

    expect(InventoryMovement::count())->toBe(0);
});

it('returns false when reserved qty leaves no available stock', function (): void {
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 10, 'qty_reserved' => 8]);
    $order = Order::create(['inventory_id' => $inventory->id, 'qty' => 5, 'status' => OrderStatus::Pending]);

    expect($this->service->reserve($order))->toBeFalse();
});

it('force reserves regardless of available stock and increments qty_total', function (): void {
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 0, 'qty_reserved' => 0]);
    $order = Order::create(['inventory_id' => $inventory->id, 'qty' => 3, 'status' => OrderStatus::AwaitingRestock]);

    $this->service->forceReserve($order);

    expect($order->fresh()->status)->toBe(OrderStatus::Reserved)
        ->and($inventory->fresh()->qty_total)->toBe(3)
        ->and($inventory->fresh()->qty_reserved)->toBe(3);

    expect(InventoryMovement::where('type', 'supplier_reservation')->exists())->toBeTrue();
});

it('creates inventory movement with correct negative qty_change on reserve', function (): void {
    $inventory = Inventory::create(['sku' => 'ABC', 'qty_total' => 10, 'qty_reserved' => 0]);
    $order = Order::create(['inventory_id' => $inventory->id, 'qty' => 4, 'status' => OrderStatus::Pending]);

    $this->service->reserve($order);

    $movement = InventoryMovement::where('order_id', $order->id)->first();
    expect($movement->qty_change)->toBe(-4)
        ->and($movement->type)->toBe('reservation');
});
