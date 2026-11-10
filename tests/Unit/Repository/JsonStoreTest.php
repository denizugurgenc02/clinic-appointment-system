<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Repository\Json\FileLockTransactionManager;
use App\Repository\Json\JsonStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonStoreTest extends TestCase
{
    private string $dir;
    private JsonStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/json-store-' . bin2hex(random_bytes(4));
        $this->store = new JsonStore($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter(glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
        @rmdir($this->dir);
    }

    public function testInsertAssignsIncrementingIdsAndUpdateMerges(): void
    {
        $first = $this->store->insert('departments', ['name' => 'Dahiliye']);
        $second = $this->store->insert('departments', ['name' => 'Göz']);
        $this->store->update('departments', $second, ['name' => 'Göz Hastalıkları']);

        self::assertSame([1, 2], [$first, $second]);
        self::assertSame('Göz Hastalıkları', $this->store->find('departments', 2)['name'] ?? null);
    }

    public function testFailedTransactionRollsBackAllWrittenTables(): void
    {
        $this->store->insert('appointments', ['status' => 'booked']);
        $tx = new FileLockTransactionManager($this->store, $this->dir . '/.lock');

        try {
            $tx->run(function (): void {
                $this->store->update('appointments', 1, ['status' => 'cancelled']);
                $this->store->insert('waitlist', ['status' => 'offered']);
                throw new RuntimeException('İkinci adımda hata');
            });
        } catch (RuntimeException) {
        }

        self::assertSame('booked', $this->store->find('appointments', 1)['status'] ?? null, 'Güncelleme geri alınmalı');
        self::assertSame([], $this->store->rows('waitlist'), 'Ekleme geri alınmalı');
    }
}
