<?php

declare(strict_types=1);

namespace App\Service\Exception;

/**
 * Form doğrulama hataları. Alan adı => hata mesajı.
 * Controller, girilen değerleri koruyarak formu bu hatalarla yeniden gösterir.
 */
final class ValidationException extends DomainRuleException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private readonly array $errors, string $message = 'Lütfen formdaki hataları düzeltin.')
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
