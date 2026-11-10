<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Service\AuthService;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\NotFoundException;
use App\Service\ProfilePhotoService;
use DomainException;

/**
 * Profil ve profil fotoğrafı (güvenli dosya yükleme).
 */
final class ProfileController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly ProfilePhotoService $photos,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function show(Request $request): Response
    {
        return $this->render('profile/index', ['title' => 'Profilim', 'user' => $this->user()]);
    }

    public function uploadPhoto(Request $request): Response
    {
        try {
            $this->photos->upload($this->user(), $request->uploadedFile('photo'));
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/profile');
        }

        $this->auth->refresh();
        $this->success('Profil fotoğrafınız güncellendi.');

        return $this->redirect('/profile');
    }

    /**
     * GET /users/{id}/photo — dosya public/ dışında olduğu için yalnızca bu uçtan, yetki kontrolüyle sunulur.
     */
    public function photo(Request $request): Response
    {
        try {
            $photo = $this->photos->photoFor($this->user(), self::requireInt($request->param('id'), 'id'));
        } catch (AccessDeniedException $e) {
            throw HttpException::forbidden($e->getMessage());
        } catch (NotFoundException | DomainException $e) {
            throw HttpException::notFound($e->getMessage());
        }

        $content = file_get_contents($photo['path']);
        if ($content === false) {
            throw HttpException::notFound('Fotoğraf okunamadı.');
        }

        return new Response($content, 200, [
            'Content-Type' => $photo['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
