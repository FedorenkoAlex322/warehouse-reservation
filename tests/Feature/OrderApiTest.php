<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('creates order and returns 201 with pending status', function (): void {
    $response = $this->postJson('/api/order', ['sku' => 'WIDGET-001', 'qty' => 3]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'sku', 'qty', 'status']])
        ->assertJsonFragment(['sku' => 'WIDGET-001', 'qty' => 3, 'status' => 'pending']);

    $this->assertDatabaseHas('orders', ['sku' => 'WIDGET-001', 'qty' => 3]);
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
    $order = Order::create(['sku' => 'WIDGET-001', 'qty' => 2, 'status' => OrderStatus::Reserved]);

    $this->getJson("/api/orders/{$order->id}")
        ->assertStatus(200)
        ->assertJsonFragment(['id' => $order->id, 'status' => 'reserved']);
});

it('returns 404 for non-existent order', function (): void {
    $this->getJson('/api/orders/9999')->assertStatus(404);
});
