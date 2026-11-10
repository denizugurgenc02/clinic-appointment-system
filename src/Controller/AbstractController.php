<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Model\User;
use App\Service\AuthService;
use App\Service\Exception\ValidationException;
use DateTimeImmutable;
use DomainException;

/**
 * Controller'ların ortak yardımcıları. İş mantığı içermez: yalnızca girdi okuma,
 * yanıt üretme ve hata mesajlarını kullanıcıya taşıma.
 */
abstract class AbstractController
{
    public function __construct(
        protected readonly View $view,
        protected readonly Session $session,
        protected readonly AuthService $auth,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data), $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    /**
     * Geldiği sayfaya döner. Yalnızca yol + sorgu kullanılır; dış siteye yönlendirme yapılamaz.
     */
    protected function back(Request $request, string $fallback): Response
    {
        $referer = $request->header('referer');
        $path = $referer === null ? null : parse_url($referer, PHP_URL_PATH);

        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $this->redirect($fallback);
        }

        $query = parse_url((string) $referer, PHP_URL_QUERY);

        return $this->redirect($path . (is_string($query) && $query !== '' ? '?' . $query : ''));
    }

    /**
     * Oturumdaki kullanıcı (rol middleware'i bu noktaya gelmeden kontrol etmiştir).
     */
    protected function user(): User
    {
        return $this->auth->user() ?? throw new HttpException(401, 'Bu işlem için giriş yapmanız gerekiyor.');
    }

    protected function success(string $message): void
    {
        $this->session->flash('success', $message);
    }

    /**
     * İş kuralı hatasını kullanıcıya taşır: mesaj + alan hataları + girilen değerler korunur.
     */
    protected function failed(Request $request, DomainException $e, string $fallback): Response
    {
        $this->session->flash('danger', $e->getMessage());

        if ($e instanceof ValidationException) {
            $this->session->flashErrors($e->errors());
        }

        $this->session->flashInput($request->all());

        return $this->back($request, $fallback);
    }

    protected static function toInt(?string $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $int === false ? null : $int;
    }

    /**
     * Biçime tam uyan tarih; uymuyorsa null. "!" saatin sıfırlanmasını sağlar.
     */
    protected static function toDate(?string $value, string $format): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    protected static function requireInt(?string $value, string $field): int
    {
        return self::toInt($value) ?? throw new ValidationException([$field => 'Lütfen geçerli bir seçim yapın.']);
    }

    protected static function requireDate(?string $value, string $format, string $field): DateTimeImmutable
    {
        return self::toDate($value, $format) ?? throw new ValidationException([$field => 'Lütfen geçerli bir tarih/saat seçin.']);
    }
}
