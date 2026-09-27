<?php
namespace App\Domain\Commerce\Enums;
enum OrderStatus: string { case PendingPayment='pending_payment'; case Confirmed='confirmed'; case Cancelled='cancelled'; }
