<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Repository\Contract\TransactionManagerInterface;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Aşama 2 transaction'ı: tek bir kilit dosyasına özel kilit (flock LOCK_EX).
 *
 * İki istek aynı anda randevu almaya çalışırsa ikincisi, birincisi işini bitirip kilidi
 * bırakana kadar flock() satırında bekler; sonra güncel dosyayı okur ve slotu dolu görür.
 * İş sırasında hata olursa JsonStore değişen tabloları eski haline döndürür (rollback).
 *
 * Aşama 3'te yerini PdoTransactionManager (BEGIN/COMMIT + SELECT ... FOR UPDATE) alır.
 */
final class FileLockTransactionManager implements TransactionManagerInterface
{
    private int $depth = 0;

    public function __construct(
        private readonly JsonStore $store,
        private readonly string $lockFile,
    ) {
    }

    public function run(Closure $work): mixed
    {
        // İç içe çağrı: dış işlem zaten kilidi tutuyor
        if ($this->depth > 0) {
            $this->depth++;
            try {
                return $work();
            } finally {
                $this->depth--;
            }
        }

        $handle = fopen($this->lockFile, 'c');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Kilit dosyası açılamadı: %s', $this->lockFile));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Veri kilidi alınamadı.');
            }

            $this->depth = 1;
            $this->store->beginTransaction();

            try {
                $result = $work();
                $this->store->commit();

                return $result;
            } catch (Throwable $e) {
                $this->store->rollBack();
                throw $e;
            }
        } finally {
            $this->depth = 0;
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
