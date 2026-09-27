<?php
namespace App\Domain\Payments\Enums;
enum PaymentAttemptStatus:string { case Created='created'; case Open='open'; case Pending='pending'; case Paid='paid'; case Failed='failed'; case Expired='expired'; case Cancelled='cancelled'; }
