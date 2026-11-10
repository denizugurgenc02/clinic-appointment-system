<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Gelen HTTP isteğinin değişmez (immutable) temsili.
 * Süper global'ler ($_GET, $_POST ...) yalnızca fromGlobals() içinde okunur;
 * böylece controller'lar ve testler sahte istek nesnesiyle çalışabilir.
 */
final class Request
{
    /**
     * @param array<string, mixed>  $query       $_GET
     * @param array<string, mixed>  $body        $_POST
     * @param array<string, mixed>  $files       $_FILES
     * @param array<string, string> $headers     Anahtarlar küçük harf
     * @param array<string, string> $routeParams /appointments/{id} gibi yollardan gelen değerler
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $files = [],
        private readonly array $headers = [],
        private readonly array $routeParams = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return new self(
            is_string($method) ? $method : 'GET',
            is_string($uri) ? $uri : '/',
            $_GET,
            $_POST,
            $_FILES,
            $headers,
        );
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    /**
     * Sorgu dizesi olmadan, sondaki "/" kırpılmış yol. Kök için "/".
     */
    public function path(): string
    {
        $path = parse_url($this->path, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $path = '/' . trim($path, '/');

        return $path;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return self::scalarOrDefault($this->query[$key] ?? null, $default);
    }

    public function input(string $key, ?string $default = null): ?string
    {
        return self::scalarOrDefault($this->body[$key] ?? null, $default);
    }

    /**
     * Form gövdesinin tamamı (hatalı gönderimde girilen değerleri korumak için).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>|null $_FILES içindeki tek dosya kaydı
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file)) {
            return null;
        }

        /** @var array<string, mixed> $file */
        return $file;
    }

    public function uploadedFile(string $key): UploadedFile
    {
        return UploadedFile::fromArray($this->file($key) ?? []);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function param(string $name): ?string
    {
        return $this->routeParams[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function params(): array
    {
        return $this->routeParams;
    }

    /**
     * @param array<string, string> $params
     */
    public function withRouteParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $this->body, $this->files, $this->headers, $params);
    }

    /**
     * AJAX (fetch) isteği mi ya da JSON yanıt mı bekleniyor?
     */
    public function wantsJson(): bool
    {
        return str_starts_with($this->path(), '/api/')
            || str_contains($this->header('accept') ?? '', 'application/json')
            || $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    private static function scalarOrDefault(mixed $value, ?string $default): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }
}
