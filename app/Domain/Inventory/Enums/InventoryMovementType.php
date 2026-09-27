<?php
namespace App\Domain\Inventory\Enums;
enum InventoryMovementType: string
{
    case StockReceived = 'stock_received';
    case Order = 'order';
    case OrderCancelled = 'order_cancelled';
    case ReturnReceived = 'return_received';
    case Damaged = 'damaged';
    case ManualAdjustment = 'manual_adjustment';
}
