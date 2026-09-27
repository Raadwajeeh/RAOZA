<?php
namespace App\Domain\Returns\Enums;
enum ReturnResolution:string { case Pending='pending'; case Refund='refund'; case NoRefund='no_refund'; }
