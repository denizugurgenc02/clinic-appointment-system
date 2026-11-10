<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Repository\Contract\TransactionManagerInterface;
use Closure;
use Throwable;

/**
 * Bellek içi transaction: dış işlem başında anlık görüntü alır, hata olursa geri yükler.
 * Tek süreçte çalıştığı için kilide gerek yoktur (eşzamanlılık, JSON uygulamasıyla ayrıca test edilir).
 */
final class InMemoryTransactionManager implements TransactionManagerInterface
{
    private int $depth = 0;

    public function __construct(private readonly InMemoryDatabase $db)
    {
    }

    public function run(Closure $work): mixed
    {
        if ($this->depth > 0) {
            $this->depth++;
            try {
                return $work();
            } finally {
                $this->depth--;
            }
        }

        $snapshot = $this->db->snapshot();
        $this->depth = 1;

        try {
            return $work();
        } catch (Throwable $e) {
            $this->db->restore($snapshot);
            throw $e;
        } finally {
            $this->depth = 0;
        }
    }
}
