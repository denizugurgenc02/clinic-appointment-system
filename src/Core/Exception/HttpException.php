<?php

declare(strict_types=1);

namespace App\Core\Exception;

use RuntimeException;

/**
 * Kullanıcıya belirli bir HTTP durum koduyla gösterilecek hatalar.
 * Application bu istisnayı yakalar ve hata sayfası / JSON hata yanıtı üretir.
 */
class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly array $headers = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function notFound(string $message = 'Aradığınız sayfa bulunamadı.'): self
    {
        return new self(404, $message);
    }

    /**
     * @param list<string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(405, 'Bu adres için istek yöntemi desteklenmiyor.', ['Allow' => implode(', ', $allowedMethods)]);
    }

    public static function forbidden(string $message = 'Bu işlem için yetkiniz yok.'): self
    {
        return new self(403, $message);
    }

    public static function csrfMismatch(): self
    {
        return new self(419, 'Oturum süreniz dolmuş veya form geçersiz. Lütfen sayfayı yenileyip tekrar deneyin.');
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
