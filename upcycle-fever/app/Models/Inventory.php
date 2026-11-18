<?php

namespace App\Models;

/**
 * Class Inventory
 *
 * Backward-compatible alias for InventoryItem.
 */
class Inventory extends InventoryItem
{
    protected $table = 'inventory_items';
}
