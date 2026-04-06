<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Inventory;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['sku' => 'WIDGET-001', 'qty_total' => 100, 'qty_reserved' => 0],
            ['sku' => 'WIDGET-002', 'qty_total' => 5, 'qty_reserved' => 0],
            ['sku' => 'GADGET-001', 'qty_total' => 0, 'qty_reserved' => 0],
        ];

        foreach ($items as $item) {
            Inventory::create($item);
        }
    }
}
