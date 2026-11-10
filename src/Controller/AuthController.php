<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use DomainException;

final class AuthController extends AbstractController
{
    public function showLogin(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user !== null) {
            return $this->redirect($user->role->homePath());
        }

        return $this->render('auth/login', ['title' => 'Giriş Yap']);
    }

    public function login(Request $request): Response
    {
        try {
            $user = $this->auth->attempt((string) $request->input('email'), (string) $request->input('password'));
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/login');
        }

        $this->success('Hoş geldiniz, ' . $user->name . '.');

        return $this->redirect($user->role->homePath());
    }

    public function showRegister(Request $request): Response
    {
        if ($this->auth->user() !== null) {
            return $this->redirect('/');
        }

        return $this->render('auth/register', ['title' => 'Kayıt Ol']);
    }

    public function register(Request $request): Response
    {
        try {
            $this->auth->register(
                (string) $request->input('name'),
                (string) $request->input('email'),
                (string) $request->input('password'),
                (string) $request->input('password_confirmation'),
                (string) $request->input('phone'),
            );
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/register');
        }

        $this->success('Kaydınız oluşturuldu. Şimdi randevu alabilirsiniz.');

        return $this->redirect('/appointments/book');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        $this->success('Çıkış yaptınız.');

        return $this->redirect('/login');
    }
}
