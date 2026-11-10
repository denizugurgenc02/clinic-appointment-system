<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * $_SESSION erişimini tek noktada toplar: oturum başlatma, kimlik yenileme,
 * tek seferlik (flash) mesajlar ve hatalı formda girilen değerlerin korunması.
 */
final class Session
{
    private const FLASH_KEY = '_flash';
    private const OLD_INPUT_KEY = '_old_input';

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            $_SESSION ??= [];

            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Değeri okur ve oturumdan siler.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    /**
     * Girişte oturum sabitleme (session fixation) saldırısını önlemek için çağrılır.
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Bir sonraki sayfada bir kez gösterilecek mesaj. $type: success|danger|warning|info
     */
    public function flash(string $type, string $message): void
    {
        $flashes = $this->flashes();
        $flashes[$type][] = $message;
        $_SESSION[self::FLASH_KEY] = $flashes;
    }

    /**
     * Bekleyen flash mesajlarını döndürür ve siler.
     *
     * @return array<string, list<string>>
     */
    public function pullFlashes(): array
    {
        $flashes = $this->flashes();
        $this->remove(self::FLASH_KEY);

        return $flashes;
    }

    /**
     * Hatalı form gönderiminde girilen değerleri bir sonraki istek için saklar.
     *
     * @param array<string, mixed> $input
     */
    public function flashInput(array $input): void
    {
        unset($input['_csrf'], $input['password'], $input['password_confirmation']);
        $_SESSION[self::OLD_INPUT_KEY] = $input;
    }

    /**
     * @return array<string, mixed>
     */
    public function pullOldInput(): array
    {
        $old = $this->pull(self::OLD_INPUT_KEY, []);

        return is_array($old) ? $old : [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function flashes(): array
    {
        $flashes = $_SESSION[self::FLASH_KEY] ?? [];

        if (!is_array($flashes)) {
            return [];
        }

        $clean = [];
        foreach ($flashes as $type => $messages) {
            if (is_string($type) && is_array($messages)) {
                $clean[$type] = array_values(array_filter($messages, 'is_string'));
            }
        }

        return $clean;
    }
}
