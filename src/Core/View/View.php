<?php

declare(strict_types=1);

namespace App\Core\View;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * views/ altındaki PHP şablonlarını render eder.
 *
 * - render():  şablonu bir layout (varsayılan layouts/main) içine yerleştirir.
 * - partial(): layout olmadan yalnızca parçayı üretir.
 * - share():   tüm şablonlara verilecek ortak değişkenler (CSRF jetonu, flash mesajları ...).
 *
 * Şablonlar yalnızca gösterim yapar; tüm değerler e() ile kaçışlanarak basılır.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $basePath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], string $layout = 'layouts/main'): string
    {
        $content = $this->partial($template, $data);

        return $this->partial($layout, [...$data, 'content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->resolve($template);

        return self::evaluate($file, [...$this->shared, ...$data]);
    }

    private function resolve(string $template): string
    {
        // "../" ile views/ dışına çıkılmasını engelle
        if (preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#i', $template) !== 1) {
            throw new InvalidArgumentException(sprintf('Geçersiz şablon adı: "%s"', $template));
        }

        $file = rtrim($this->basePath, '/\\') . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Şablon bulunamadı: "%s"', $template));
        }

        return $file;
    }

    /**
     * Şablonu yalıtılmış bir kapsamda çalıştırır; $this ve View'in iç
     * değişkenleri şablona sızmaz.
     *
     * @param array<string, mixed> $__data
     */
    private static function evaluate(string $__file, array $__data): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            (static function () use ($__file, $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })();

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
