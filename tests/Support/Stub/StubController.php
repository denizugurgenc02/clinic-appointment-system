<?php

declare(strict_types=1);

namespace Tests\Support\Stub;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class StubController
{
    public function show(Request $request): Response
    {
        return Response::html('randevu:' . $request->param('id'));
    }

    public function notAResponse(Request $request): string
    {
        return 'hata';
    }
}
