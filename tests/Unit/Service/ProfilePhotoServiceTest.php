<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Http\UploadedFile;
use App\Model\Patient;
use App\Repository\Contract\UserRepositoryInterface;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\DomainRuleException;
use App\Service\ProfilePhotoService;
use Tests\Support\AppTestCase;

/**
 * Güvenli dosya yükleme: içerik kontrolü, boyut sınırı, rastgele ad, yetkili erişim.
 */
final class ProfilePhotoServiceTest extends AppTestCase
{
    /** 1x1 piksel geçerli PNG */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private ProfilePhotoService $photos;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->photos = $this->get(ProfilePhotoService::class);
        $this->patient = $this->patient();
    }

    private function upload(string $content, string $clientName): UploadedFile
    {
        $path = $this->dataDir . '/upload-' . bin2hex(random_bytes(4));
        file_put_contents($path, $content);

        return new UploadedFile($clientName, $path, strlen($content), UPLOAD_ERR_OK, fromHttpUpload: false);
    }

    public function testValidImageIsStoredWithRandomName(): void
    {
        $filename = $this->photos->upload($this->patient, $this->upload((string) base64_decode(self::PNG), '../../evil name.png'));

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $filename, 'İstemcinin verdiği ad kullanılmamalı');
        self::assertFileExists($this->dataDir . '/uploads/' . $filename);
        self::assertSame($filename, $this->get(UserRepositoryInterface::class)->find($this->patient->id())?->photoPath);
    }

    public function testPhpFileDisguisedAsPngIsRejected(): void
    {
        $this->expectException(DomainRuleException::class);

        $this->photos->upload($this->patient, $this->upload('<?php system($_GET["c"]); ?>', 'avatar.png'));
    }

    public function testOversizedFileIsRejected(): void
    {
        $file = new UploadedFile('big.png', '/tmp/none', 3 * 1024 * 1024, UPLOAD_ERR_OK, fromHttpUpload: false);

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('2 MB');

        $this->photos->upload($this->patient, $file);
    }

    public function testOtherPatientsCannotSeeAPatientsPhoto(): void
    {
        $this->photos->upload($this->patient, $this->upload((string) base64_decode(self::PNG), 'a.png'));

        self::assertSame('image/png', $this->photos->photoFor($this->patient, $this->patient->id())['mime']);

        $this->expectException(AccessDeniedException::class);
        $this->photos->photoFor($this->patient('Meraklı'), $this->patient->id());
    }
}
