<?php

declare(strict_types=1);

namespace App\Service\Exception;

/**
 * Seçilen slot bu arada başka bir hasta tarafından alındı (K6).
 */
final class SlotAlreadyTakenException extends DomainRuleException
{
}
