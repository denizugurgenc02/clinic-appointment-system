<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * Oturum başına bir CSRF jetonu üretir ve gelen jetonu sabit zamanlı karşılaştırır.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function isValid(?string $token): bool
    {
        $expected = $this->session->get(self::KEY);

        return is_string($expected)
            && $expected !== ''
            && $token !== null
            && hash_equals($expected, $token);
    }
}
