<?php

declare(strict_types=1);

namespace App\Model;

final class Department
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $description = '',
    ) {
    }
}
