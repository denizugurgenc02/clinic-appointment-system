<?php

declare(strict_types=1);

namespace App\Core\Http;

use RuntimeException;

/**
 * $_FILES kaydının tipli temsili.
 *
 * Gerçek yüklemelerde dosya yalnızca move_uploaded_file() ile taşınır; bu fonksiyon
 * dosyanın gerçekten HTTP ile yüklendiğini doğrular. Testlerde $fromHttpUpload=false
 * verilerek normal bir dosya kullanılabilir.
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $clientName,
        public readonly string $tmpPath,
        public readonly int $size,
        public readonly int $error,
        private readonly bool $fromHttpUpload = true,
    ) {
    }

    /**
     * @param array<string, mixed> $file $_FILES['alan']
     */
    public static function fromArray(array $file): self
    {
        return new self(
            is_string($file['name'] ?? null) ? $file['name'] : '',
            is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '',
            is_int($file['size'] ?? null) ? $file['size'] : 0,
            is_int($file['error'] ?? null) ? $file['error'] : UPLOAD_ERR_NO_FILE,
        );
    }

    public function moveTo(string $target): void
    {
        $moved = $this->fromHttpUpload
            ? is_uploaded_file($this->tmpPath) && move_uploaded_file($this->tmpPath, $target)
            : rename($this->tmpPath, $target);

        if (!$moved) {
            throw new RuntimeException('Yüklenen dosya kaydedilemedi.');
        }
    }
}
