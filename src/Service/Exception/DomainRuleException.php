<?php

declare(strict_types=1);

namespace App\Service\Exception;

/**
 * Kullanıcıya gösterilebilecek iş kuralı ihlali.
 * Controller'lar bu istisnanın mesajını flash mesajı olarak gösterir.
 */
class DomainRuleException extends \DomainException
{
}
