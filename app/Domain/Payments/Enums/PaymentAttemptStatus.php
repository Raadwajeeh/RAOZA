<?php

namespace App\Domain\Payments\Enums;

enum PaymentAttemptStatus: string
{
    case Created = 'created';
    case Creating = 'creating';
    case Open = 'open';
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case ReviewRequired = 'review_required';

}
