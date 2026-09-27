<?php
namespace App\Domain\Fulfillment\Enums;
enum ShipmentStatus:string {case Pending='pending';case LabelCreated='label_created';case Shipped='shipped';case InTransit='in_transit';case Delivered='delivered';case Exception='exception';case Cancelled='cancelled';case Returned='returned';}
