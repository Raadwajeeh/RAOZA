<?php
namespace App\Domain\Commerce\Enums;
enum FulfillmentStatus: string { case Unfulfilled='unfulfilled'; case Processing='processing'; case Printing='printing'; case ReadyToShip='ready_to_ship'; case Fulfilled='fulfilled'; case OnHold='on_hold'; case Cancelled='cancelled'; }
