<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;

final class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        // message - generic by ADR-10/auth.failed, do not revale the user’s existence.
        parent::__construct(
            message: 'These credentials do not match our records.',
            errorCode: 'auth.invalid_credentials',
            statusCode: 401,
        );
    }
}