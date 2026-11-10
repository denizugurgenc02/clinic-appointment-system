<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testPathIsNormalized(): void
    {
        self::assertSame('/', (new Request('GET', ''))->path());
        self::assertSame('/', (new Request('GET', '/?a=1'))->path());
        self::assertSame('/appointments/5', (new Request('GET', '/appointments/5/?x=1'))->path());
    }

    public function testNonScalarInputFallsBackToDefault(): void
    {
        $request = new Request('POST', '/', ['doctor_id' => ['1', '2']], ['note' => 'Baş ağrısı']);

        self::assertNull($request->query('doctor_id'));
        self::assertSame('Baş ağrısı', $request->input('note'));
        self::assertSame('x', $request->input('missing', 'x'));
    }

    public function testWantsJsonForApiPathsAndAjaxHeaders(): void
    {
        self::assertTrue((new Request('GET', '/api/slots'))->wantsJson());
        self::assertTrue((new Request('GET', '/x', headers: ['accept' => 'application/json']))->wantsJson());
        self::assertFalse((new Request('GET', '/appointments'))->wantsJson());
    }
}
