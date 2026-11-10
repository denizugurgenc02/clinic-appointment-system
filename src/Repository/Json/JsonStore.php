<?php

declare(strict_types=1);

namespace App\Repository\Json;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Aşama 2 veri deposu: her tablo bir JSON dosyasıdır (storage/data/<tablo>.json).
 *
 * Dosya biçimi: {"auto_increment": 12, "rows": [{...}, {...}]}
 *
 * - Yazma: önce geçici dosyaya yazılır, sonra rename() ile atomik olarak değiştirilir;
 *   okuyan bir istek hiçbir zaman yarım yazılmış dosya görmez.
 * - İşlem (transaction): beginTransaction() sonrası ilk değişiklikte tablonun eski hali
 *   saklanır; rollBack() eski halleri geri yazar. Kilitleme FileLockTransactionManager'dadır.
 * - Önbellek yoktur: her okuma diskten yapılır, böylece kilidi alan istek en güncel veriyi görür.
 */
final class JsonStore
{
    /** @var array<string, array{auto_increment: int, rows: list<array<string, mixed>>}>|null */
    private ?array $snapshot = null;

    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Veri klasörü oluşturulamadı: %s', $directory));
        }
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $table): array
    {
        return $this->load($table)['rows'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $table, int $id, string $key = 'id'): ?array
    {
        foreach ($this->rows($table) as $row) {
            if (($row[$key] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Otomatik artan id ile ekler ve id'yi döndürür.
     *
     * @param array<string, mixed> $row
     */
    public function insert(string $table, array $row): int
    {
        $data = $this->load($table);
        $id = $data['auto_increment'] + 1;
        $data['auto_increment'] = $id;
        $data['rows'][] = ['id' => $id] + $row;
        $this->save($table, $data);

        return $id;
    }

    /**
     * Anahtarı dışarıdan verilen satırı ekler (doctors/patients: user_id).
     *
     * @param array<string, mixed> $row
     */
    public function insertRaw(string $table, array $row): void
    {
        $data = $this->load($table);
        $data['rows'][] = $row;
        $this->save($table, $data);
    }

    /**
     * @param array<string, mixed> $values Güncellenecek sütunlar
     */
    public function update(string $table, int $id, array $values, string $key = 'id'): void
    {
        $data = $this->load($table);

        foreach ($data['rows'] as $index => $row) {
            if (($row[$key] ?? null) === $id) {
                $data['rows'][$index] = array_merge($row, $values);
                $this->save($table, $data);

                return;
            }
        }

        throw new RuntimeException(sprintf('%s tablosunda %s=%d bulunamadı.', $table, $key, $id));
    }

    public function delete(string $table, int $id, string $key = 'id'): void
    {
        $data = $this->load($table);
        $data['rows'] = array_values(array_filter(
            $data['rows'],
            static fn (array $row): bool => ($row[$key] ?? null) !== $id,
        ));
        $this->save($table, $data);
    }

    public function truncate(string $table): void
    {
        $this->save($table, ['auto_increment' => 0, 'rows' => []]);
    }

    public function beginTransaction(): void
    {
        $this->snapshot = [];
    }

    public function commit(): void
    {
        $this->snapshot = null;
    }

    public function rollBack(): void
    {
        foreach ($this->snapshot ?? [] as $table => $data) {
            $this->persist($table, $data);
        }

        $this->snapshot = null;
    }

    /**
     * @param array{auto_increment: int, rows: list<array<string, mixed>>} $data
     */
    private function save(string $table, array $data): void
    {
        // İşlem içindeki ilk değişiklikten önce tablonun diskteki halini sakla
        if ($this->snapshot !== null && !array_key_exists($table, $this->snapshot)) {
            $this->snapshot[$table] = $this->load($table);
        }

        $this->persist($table, $data);
    }

    /**
     * @return array{auto_increment: int, rows: list<array<string, mixed>>}
     */
    private function load(string $table): array
    {
        $path = $this->path($table);

        if (!is_file($path)) {
            return ['auto_increment' => 0, 'rows' => []];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException(sprintf('%s okunamadı.', $path));
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('%s geçerli JSON değil: %s', $path, $e->getMessage()), 0, $e);
        }

        if (!is_array($data) || !is_int($data['auto_increment'] ?? null) || !is_array($data['rows'] ?? null)) {
            throw new RuntimeException(sprintf('%s beklenen biçimde değil.', $path));
        }

        $rows = [];
        foreach ($data['rows'] as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return ['auto_increment' => $data['auto_increment'], 'rows' => $rows];
    }

    /**
     * @param array{auto_increment: int, rows: list<array<string, mixed>>} $data
     */
    private function persist(string $table, array $data): void
    {
        $path = $this->path($table);
        $temp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($temp, $json . "\n") === false || !rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException(sprintf('%s yazılamadı.', $path));
        }
    }

    private function path(string $table): string
    {
        if (preg_match('/^[a-z_]+$/', $table) !== 1) {
            throw new InvalidArgumentException(sprintf('Geçersiz tablo adı: %s', $table));
        }

        return $this->directory . '/' . $table . '.json';
    }
}
