<?php

declare(strict_types=1);

use App\Enums\OrderStatus;

it('has correct string values', function (): void {
    expect(OrderStatus::Pending->value)->toBe('pending')
        ->and(OrderStatus::Reserved->value)->toBe('reserved')
        ->and(OrderStatus::AwaitingRestock->value)->toBe('awaiting_restock')
        ->and(OrderStatus::Failed->value)->toBe('failed');
});

it('marks reserved and failed as terminal', function (): void {
    expect(OrderStatus::Reserved->isTerminal())->toBeTrue()
        ->and(OrderStatus::Failed->isTerminal())->toBeTrue();
});

it('marks pending and awaiting_restock as non-terminal', function (): void {
    expect(OrderStatus::Pending->isTerminal())->toBeFalse()
        ->and(OrderStatus::AwaitingRestock->isTerminal())->toBeFalse();
});
