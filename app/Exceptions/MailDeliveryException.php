<?php

namespace App\Exceptions;

use RuntimeException;

/** The account action succeeded or was saved; only SMTP delivery failed. */
class MailDeliveryException extends RuntimeException {}
