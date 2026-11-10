<?php

namespace App\Bank;

/** The parser cannot run until an operator fixes the server. Retrying will not help. */
class ParserConfigurationException extends ProcessingException
{
    public const CUSTOMER_MESSAGE = "Document reading isn't set up on this server yet, so contact support";

    public function __construct()
    {
        parent::__construct(self::CUSTOMER_MESSAGE);
    }
}
