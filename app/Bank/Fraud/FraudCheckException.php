<?php

namespace App\Bank\Fraud;

/** Customer-safe message only. Never attach provider bodies, keys, or document text. */
class FraudCheckException extends \RuntimeException
{
    public function __construct(string $customerMessage, public bool $retryable = false)
    {
        parent::__construct($customerMessage);
    }
}
