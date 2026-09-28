<?php

namespace App\Domain\Payments\Exceptions;

use RuntimeException;
use Throwable;

final class ProviderOperationException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $outcomeUncertain, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
