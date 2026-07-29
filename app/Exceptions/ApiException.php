<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Base class for domain errors that frontends can perform.
 * Localize by stable code instead of message text.
 *
 * Messages remain in backend log/telescope/debug -
 * The front end does not display directly to the user.
 */
abstract class ApiException extends Exception
{
    public function __construct(string $message, protected readonly string $errorCode, protected readonly int $statusCode = 400) 
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}