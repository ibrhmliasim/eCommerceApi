<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\RegisterRequest;

// readonly
readonly class RegisterDTO
{
    public function __construct(
        public string  $first_name,
        public string  $last_name,
        public string  $email,
        public string  $password,
        public ?string $phone = null,
    ) {}
    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            first_name: $request->validated('first_name'),
            last_name:  $request->validated('last_name'),
            email:      $request->validated('email'),
            password:   $request->validated('password'),
            phone:      $request->validated('phone'),
        );
    }
}