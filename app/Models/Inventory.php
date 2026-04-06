<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function availableQty(): int
    {
        return $this->qty_total - $this->qty_reserved;
    }
}
