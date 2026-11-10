<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Http\UploadedFile;
use App\Model\Enum\UserRole;
use App\Model\User;
use App\Repository\Contract\UserRepositoryInterface;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\DomainRuleException;
use App\Service\Exception\NotFoundException;
use finfo;

/**
 * Güvenli profil fotoğrafı yükleme:
 *  1. Yükleme hatası ve boyut kontrolü (≤ 2 MB)
 *  2. Uzantıya/tarayıcının söylediğine değil, dosya içeriğine bakılarak MIME türü (finfo)
 *  3. getimagesize() ile gerçekten görüntü olduğunun doğrulanması
 *  4. Rastgele dosya adı (bin2hex(random_bytes)) + beyaz listeden uzantı
 *  5. public/ dışında (storage/uploads) saklama; yalnızca yetkili kullanıcıya sunma
 */
final class ProfilePhotoService
{
    /**
     * @param array<string, string> $allowedMime MIME => uzantı
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly string $uploadDirectory,
        private readonly int $maxBytes,
        private readonly array $allowedMime,
    ) {
    }

    public function upload(User $user, UploadedFile $file): string
    {
        if ($user->is(UserRole::SuperAdmin)) {
            throw new DomainRuleException('Yerleşik yönetici hesabı için profil fotoğrafı yüklenemez.');
        }
        if ($file->error === UPLOAD_ERR_NO_FILE) {
            throw new DomainRuleException('Lütfen bir fotoğraf seçin.');
        }
        if ($file->error === UPLOAD_ERR_INI_SIZE || $file->error === UPLOAD_ERR_FORM_SIZE || $file->size > $this->maxBytes) {
            throw new DomainRuleException(sprintf('Fotoğraf en fazla %d MB olabilir.', intdiv($this->maxBytes, 1024 * 1024)));
        }
        if ($file->error !== UPLOAD_ERR_OK || !is_file($file->tmpPath)) {
            throw new DomainRuleException('Dosya yüklenemedi, lütfen tekrar deneyin.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->tmpPath);
        $extension = is_string($mime) ? ($this->allowedMime[$mime] ?? null) : null;

        if ($extension === null || @getimagesize($file->tmpPath) === false) {
            throw new DomainRuleException('Yalnızca JPG, PNG veya WEBP fotoğraf yükleyebilirsiniz.');
        }

        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0775, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->moveTo($this->uploadDirectory . '/' . $filename);

        $this->deleteFile($user->photoPath);
        $this->users->updatePhoto($user->id(), $filename);
        $user->photoPath = $filename;

        return $filename;
    }

    /**
     * Fotoğrafı görme yetkisi: kendisi, sekreter veya fotoğraf bir doktora aitse herkes.
     *
     * @return array{path: string, mime: string}
     */
    public function photoFor(User $viewer, int $ownerId): array
    {
        $owner = $this->users->find($ownerId) ?? throw new NotFoundException('Kullanıcı bulunamadı.');

        $allowed = $viewer->id === $ownerId
            || $viewer->is(UserRole::Secretary)
            || $viewer->hasFullAccess()
            || $owner->is(UserRole::Doctor);
        if (!$allowed) {
            throw new AccessDeniedException('Bu fotoğrafı görme yetkiniz yok.');
        }

        $path = $this->pathOf($owner->photoPath);
        if ($path === null || !is_file($path)) {
            throw new NotFoundException('Fotoğraf bulunamadı.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return ['path' => $path, 'mime' => is_string($mime) ? $mime : 'application/octet-stream'];
    }

    private function deleteFile(?string $filename): void
    {
        $path = $this->pathOf($filename);

        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Yalnızca bizim ürettiğimiz biçimdeki adlar kabul edilir (yol geçişi engeli).
     */
    private function pathOf(?string $filename): ?string
    {
        if ($filename === null || preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename) !== 1) {
            return null;
        }

        return $this->uploadDirectory . '/' . $filename;
    }
}
