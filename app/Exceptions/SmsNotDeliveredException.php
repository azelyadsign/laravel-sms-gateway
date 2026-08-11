<?php

namespace App\Exceptions;

use RuntimeException;

class SmsNotDeliveredException extends RuntimeException
{
    public function __construct(string $smsLogId)
    {
        parent::__construct("SMS {$smsLogId} was not confirmed delivered by phone.");
    }
}
