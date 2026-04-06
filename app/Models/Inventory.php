<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Inventory extends Model
{
    protected $fillable = [
        'sku',
        'qty_total',
        'qty_reserved',
    ];

    protected $casts = [
        'qty_total' => 'integer',
        'qty_reserved' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function movements(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryMovement::class, Order::class);
    }

    public function availableQty(): int
    {
        return $this->qty_total - $this->qty_reserved;
    }
}
