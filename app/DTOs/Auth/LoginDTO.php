<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\LoginRequest;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email:    $request->validated('email'),
            password: $request->validated('password'),
        );
    }
}