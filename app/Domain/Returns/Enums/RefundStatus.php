<?php
namespace App\Domain\Returns\Enums;
enum RefundStatus:string { case Requested='requested'; case Processing='processing'; case Succeeded='succeeded'; case Failed='failed'; case Cancelled='cancelled'; }
