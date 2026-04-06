<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\Order;

it('returns empty array when no movements exist for sku', function (): void {
    $this->getJson('/api/inventory/UNKNOWN-SKU/movements')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns movements with correct shape', function (): void {
    $order = Order::create(['sku' => 'WIDGET-001', 'qty' => 3, 'status' => OrderStatus::Reserved]);
    InventoryMovement::create([
        'sku' => 'WIDGET-001',
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
    $order1 = Order::create(['sku' => 'SKU-A', 'qty' => 2, 'status' => OrderStatus::Reserved]);
    $order2 = Order::create(['sku' => 'SKU-A', 'qty' => 5, 'status' => OrderStatus::Reserved]);

    InventoryMovement::create([
        'sku' => 'SKU-A',
        'qty_change' => -2,
        'type' => 'reservation',
        'order_id' => $order1->id,
    ]);
    InventoryMovement::create([
        'sku' => 'SKU-A',
        'qty_change' => -5,
        'type' => 'reservation',
        'order_id' => $order2->id,
    ]);

    $this->getJson('/api/inventory/SKU-A/movements')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('does not return movements for other skus', function (): void {
    $order = Order::create(['sku' => 'SKU-A', 'qty' => 1, 'status' => OrderStatus::Reserved]);
    InventoryMovement::create([
        'sku' => 'SKU-A',
        'qty_change' => -1,
        'type' => 'reservation',
        'order_id' => $order->id,
    ]);

    $this->getJson('/api/inventory/SKU-B/movements')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});
