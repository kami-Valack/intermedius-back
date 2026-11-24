<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    public function __construct(
        string $message,
        public string $codeKey,
        public int $status = 409
    ) {
        parent::__construct($message, $status);
    }
}
