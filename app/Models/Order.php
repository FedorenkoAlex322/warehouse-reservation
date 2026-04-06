<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'sku',
        'qty',
        'status',
        'supplier_ref',
        'attempt_count',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'qty' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
