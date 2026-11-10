<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container;
use App\Core\Exception\ContainerException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Stub\CycleA;
use Tests\Support\Stub\GreeterInterface;
use Tests\Support\Stub\Greeting;
use Tests\Support\Stub\TurkishGreeter;

final class ContainerTest extends TestCase
{
    public function testAutowiresConstructorDependenciesThroughInterfaceBinding(): void
    {
        $container = new Container();
        $container->singleton(GreeterInterface::class, static fn (): GreeterInterface => new TurkishGreeter());

        $greeting = $container->get(Greeting::class);

        self::assertSame('Merhaba', $greeting->greeter->greet());
        self::assertSame(1, $greeting->times, 'Varsayılan değerli skaler parametre kullanılmalı');
    }

    public function testSingletonReturnsSameInstanceButBindReturnsNewOne(): void
    {
        $container = new Container();
        $container->singleton(GreeterInterface::class, static fn (): GreeterInterface => new TurkishGreeter());
        $container->bind(TurkishGreeter::class, static fn (): TurkishGreeter => new TurkishGreeter());

        self::assertSame($container->get(GreeterInterface::class), $container->get(GreeterInterface::class));
        self::assertNotSame($container->get(TurkishGreeter::class), $container->get(TurkishGreeter::class));
    }

    public function testUnboundInterfaceThrowsHelpfulException(): void
    {
        $this->expectException(ContainerException::class);

        (new Container())->get(GreeterInterface::class);
    }

    public function testCircularDependencyIsDetected(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Döngüsel');

        (new Container())->get(CycleA::class);
    }
}
