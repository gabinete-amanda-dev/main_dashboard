<?php

namespace App\Service\Meta\Facebook\Auth;

/**
 * Value object for Facebook credentials/environment.
 * Centralizes where we read and store auth-related values.
 */
final class User
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {}

    public static function fromEnv(): self
    {
        $email = (string) (\getenv('FACEBOOK_EMAIL') ?: ($_ENV['FACEBOOK_EMAIL'] ?? ''));
        $password = (string) (\getenv('FACEBOOK_PASSWORD') ?: ($_ENV['FACEBOOK_PASSWORD'] ?? ''));
        return new self($email, $password);
    }
}

