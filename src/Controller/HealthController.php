<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Clock\ClockInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * JSON yanıt altyapısının çalıştığını gösteren uç nokta: GET /api/health
 */
final class HealthController
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function show(Request $request): Response
    {
        return Response::json([
            'status' => 'ok',
            'time' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }
}
