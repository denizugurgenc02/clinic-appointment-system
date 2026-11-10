<?php

declare(strict_types=1);

namespace App\Service\Exception;

/**
 * Kullanıcı bu kaynak üzerinde işlem yapamaz (K8 sahiplik kontrolü).
 */
final class AccessDeniedException extends DomainRuleException
{
}
