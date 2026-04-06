<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'qty_change',
        'type',
        'order_id',
    ];

    protected $casts = [
        'qty_change' => 'integer',
        'created_at' => 'datetime',
    ];

    public function getDirectionAttribute(): string
    {
        return $this->qty_change < 0 ? 'outbound' : 'inbound';
    }

    public function getQuantityAttribute(): int
    {
        return abs($this->qty_change);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
