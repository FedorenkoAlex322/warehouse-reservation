<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => Queue::fake());

it('creates order and returns 201 with pending status', function (): void {
    Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 100, 'qty_reserved' => 0]);

    $response = $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'sku', 'qty', 'status']])
        ->assertJsonFragment(['sku' => 'WIDGET-001', 'qty' => 3, 'status' => 'pending']);

    $this->assertDatabaseHas('orders', ['qty' => 3]);
});

it('returns 404 when sku does not exist in inventory', function (): void {
    $this->postJson('/api/order', ['sku' => 'NON-EXISTENT', 'qty' => 1])
        ->assertStatus(404);
});

it('returns 422 when sku is missing', function (): void {
    $this->postJson('/api/order', ['qty' => 3])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sku']);
});

it('returns 422 when qty is missing', function (): void {
    $this->postJson('/api/order', ['sku' => 'ABC'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['qty']);
});

it('returns 422 when qty is zero', function (): void {
    $this->postJson('/api/order', ['sku' => 'ABC', 'qty' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['qty']);
});

it('returns 422 when qty is negative', function (): void {
    $this->postJson('/api/order', ['sku' => 'ABC', 'qty' => -1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['qty']);
});

it('returns 422 when qty is not an integer', function (): void {
    $this->postJson('/api/order', ['sku' => 'ABC', 'qty' => 'abc'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['qty']);
});

it('returns 422 when body is empty', function (): void {
    $this->postJson('/api/order', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sku', 'qty']);
});

it('returns order data on show', function (): void {
    $inventory = Inventory::create(['sku' => 'WIDGET-001', 'qty_total' => 100, 'qty_reserved' => 0]);
    $order = Order::create(['inventory_id' => $inventory->id, 'qty' => 2, 'status' => OrderStatus::Reserved]);

    $this->getJson("/api/orders/{$order->id}")
        ->assertStatus(200)
        ->assertJsonFragment(['id' => $order->id, 'status' => 'reserved']);
});

it('returns 404 for non-existent order', function (): void {
    $this->getJson('/api/orders/9999')->assertStatus(404);
});
