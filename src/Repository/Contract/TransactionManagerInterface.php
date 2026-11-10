<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use Closure;

/**
 * "Kontrol et + kaydet" işlemlerini atomik çalıştırır.
 *
 * - Aşama 2: FileLockTransactionManager -> flock(LOCK_EX) + hata olursa dosyaları geri yükleme
 * - Aşama 3: PdoTransactionManager      -> BEGIN / COMMIT / ROLLBACK
 *
 * İç içe çağrılar tek bir dış işlemin parçası sayılır.
 */
interface TransactionManagerInterface
{
    /**
     * @template T
     * @param Closure(): T $work
     * @return T
     */
    public function run(Closure $work): mixed;
}
