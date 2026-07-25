<?php

namespace App\Features\Payments\Exceptions;

use RuntimeException;

final class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
