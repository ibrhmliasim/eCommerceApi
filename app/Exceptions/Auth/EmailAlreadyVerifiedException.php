<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;

final class EmailAlreadyVerifiedException extends ApiException
{
    public function __construct()
    {
        // Matches with ADR-3: verify endpoint idempotency -> 409, not 200/400.
        parent::__construct(
            message: 'Email address is already verified.',
            errorCode: 'auth.email_already_verified',
            statusCode: 409,
        );
    }
}