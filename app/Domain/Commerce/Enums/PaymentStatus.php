<?php
namespace App\Domain\Commerce\Enums;
enum PaymentStatus: string { case Unpaid='unpaid'; case Pending='pending'; case Paid='paid'; case Failed='failed'; case PartiallyRefunded='partially_refunded'; case Refunded='refunded'; }
