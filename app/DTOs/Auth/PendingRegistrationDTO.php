<?php
declare(strict_types=1);

// hashedPassword — хорошее название, оно явно показывает, что пароль уже захеширован.
// fromJson() и toJson() — логично, поскольку Redis хранит JSON.
// JSON_THROW_ON_ERROR — правильно, чтобы ошибки сериализации и десериализации не оставались незамеченными.
// SensitiveParameter — Это дополнительная защита от случайного попадания пароля в логи.

namespace App\DTOs\Auth;

use SensitiveParameter;

final readonly class PendingRegistration
{
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $hashedPassword,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $phone,
    ) {}

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        return new self(
            email: $data['email'],
            hashedPassword: $data['hashed_password'],
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }

    public function toJson(): string
    {
        return json_encode([
            'email' => $this->email,
            'hashed_password' => $this->hashedPassword,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
        ], JSON_THROW_ON_ERROR);
    }
}