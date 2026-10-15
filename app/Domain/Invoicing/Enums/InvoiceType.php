<?php

namespace App\Domain\Invoicing\Enums;

enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
}
