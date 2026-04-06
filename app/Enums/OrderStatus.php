<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case AwaitingRestock = 'awaiting_restock';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Reserved, self::Failed => true,
            default => false,
        };
    }
}
