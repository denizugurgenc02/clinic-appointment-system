<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;

/**
 * Karşılama sayfası. Controller iş mantığı içermez: girdiyi alır,
 * servisi çağırır (Faz 4+), görünümü seçer ve Response döndürür.
 */
final class HomeController
{
    public function __construct(private readonly View $view)
    {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('home/index', [
            'title' => 'Klinik Randevu Sistemi',
        ]));
    }
}
