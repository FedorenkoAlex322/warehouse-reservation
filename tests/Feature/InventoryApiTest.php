<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 when no inventory exists for sku', function (): void {
    $this->getJson('/api/inventory/UNKNOWN-SKU/movements')
        ->assertStatus(404);
});

it('returns empty array when inventory exists but no movements', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 10, 'qty_reserved' => 0]);

    $this->getJson('/api/inventory/WIDGET-001/movements')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns movements with correct shape', function (): void {
    $inventory = Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 10, 'qty_reserved' => 0]);
    $order = Order::create(['inventory_id' => $inventory->id, 'qty' => 3, 'status' => OrderStatus::Reserved]);
    InventoryMovement::create([
        'qty_change' => -3,
        'type' => 'reservation',
        'order_id' => $order->id,
    ]);

    $this->getJson('/api/inventory/WIDGET-001/movements')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment([
            'sku' => 'WIDGET-001',
            'type' => 'reservation',
            'direction' => 'outbound',
            'quantity' => 3,
            'order_id' => $order->id,
        ]);
});

it('returns multiple movements ordered by created_at desc', function (): void {
    $inventory = Inventory::create(['sku' => 'SKU-A', 'qty_total' => 20, 'qty_reserved' => 0]);
    $order1 = Order::create(['inventory_id' => $inventory->id, 'qty' => 2, 'status' => OrderStatus::Reserved]);
    $order2 = Order::create(['inventory_id' => $inventory->id, 'qty' => 5, 'status' => OrderStatus::Reserved]);

    InventoryMovement::create([
        'qty_change' => -2,
        'type' => 'reservation',
        'order_id' => $order1->id,
    ]);
    InventoryMovement::create([
        'qty_change' => -5,
        'type' => 'reservation',
        'order_id' => $order2->id,
    ]);

    $this->getJson('/api/inventory/SKU-A/movements')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('does not return movements for other skus', function (): void {
    $inventoryA = Inventory::create(['sku' => 'SKU-A', 'qty_total' => 10, 'qty_reserved' => 0]);
    Inventory::create(['sku' => 'SKU-B', 'qty_total' => 10, 'qty_reserved' => 0]);
    $order = Order::create(['inventory_id' => $inventoryA->id, 'qty' => 1, 'status' => OrderStatus::Reserved]);
    InventoryMovement::create([
        'qty_change' => -1,
        'type' => 'reservation',
        'order_id' => $order->id,
    ]);

    $this->getJson('/api/inventory/SKU-B/movements')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});
