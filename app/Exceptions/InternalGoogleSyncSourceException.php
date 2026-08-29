<?php

namespace App\Exceptions;

use RuntimeException;

class InternalGoogleSyncSourceException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 500)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
