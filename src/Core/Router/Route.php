<?php

declare(strict_types=1);

namespace App\Core\Router;

use App\Core\Middleware\MiddlewareInterface;
use Closure;

/**
 * Tek bir rota: yöntem + yol kalıbı + işleyici (+ isteğe bağlı middleware).
 * Kalıptaki {ad} parçaları yakalanır: "/appointments/{id}/cancel".
 */
final class Route
{
    private readonly string $regex;

    /** @var list<class-string<MiddlewareInterface>> */
    private array $middleware = [];

    /**
     * @param array{0: class-string, 1: string}|Closure $handler
     */
    public function __construct(
        private readonly string $method,
        private readonly string $pattern,
        private readonly array|Closure $handler,
    ) {
        $this->regex = self::compile($pattern);
    }

    /**
     * @param class-string<MiddlewareInterface> ...$middleware
     */
    public function middleware(string ...$middleware): self
    {
        foreach ($middleware as $class) {
            $this->middleware[] = $class;
        }

        return $this;
    }

    /**
     * Yol bu rotaya uyuyorsa yakalanan parametreleri, uymuyorsa null döndürür.
     *
     * @return array<string, string>|null
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return array{0: class-string, 1: string}|Closure
     */
    public function handler(): array|Closure
    {
        return $this->handler;
    }

    /**
     * @return list<class-string<MiddlewareInterface>>
     */
    public function middlewareList(): array
    {
        return $this->middleware;
    }

    private static function compile(string $pattern): string
    {
        $pattern = '/' . trim($pattern, '/');
        $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($parts === false ? [] : $parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $m) === 1) {
                $regex .= '(?P<' . $m[1] . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }
}
