<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\ContainerException;
use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Basit bağımlılık enjeksiyonu (DI) konteyneri.
 *
 * - bind():      her get() çağrısında fabrikayı yeniden çalıştırır.
 * - singleton(): fabrikayı bir kez çalıştırır, aynı nesneyi döndürür.
 * - instance():  hazır bir nesneyi kaydeder.
 * - Kayıtlı olmayan somut sınıflar yapıcı (constructor) tiplerine bakılarak
 *   otomatik üretilir (autowiring) ve istek boyunca paylaşılır.
 *
 * Arayüzler (ör. AppointmentRepositoryInterface) otomatik üretilemez; hangi
 * uygulamanın (JSON / MySQL) kullanılacağı config/services.php içinde bağlanır.
 */
final class Container
{
    /** @var array<string, Closure(Container): object> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> Döngüsel bağımlılık tespiti için */
    private array $resolving = [];

    /**
     * @param Closure(Container): object $factory
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    /**
     * @param Closure(Container): object $factory
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        $object = $this->resolve($id);

        if (!$object instanceof $id) {
            throw new ContainerException(sprintf('"%s" için üretilen nesne beklenen türde değil.', $id));
        }

        return $object;
    }

    private function resolve(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException(sprintf('Döngüsel bağımlılık tespit edildi: %s', $id));
        }

        $this->resolving[$id] = true;

        try {
            if (isset($this->factories[$id])) {
                $object = ($this->factories[$id])($this);
                if ($this->shared[$id]) {
                    $this->instances[$id] = $object;
                }

                return $object;
            }

            $object = $this->autowire($id);
            $this->instances[$id] = $object;

            return $object;
        } finally {
            unset($this->resolving[$id]);
        }
    }

    private function autowire(string $id): object
    {
        if (!class_exists($id)) {
            throw new ContainerException(sprintf('"%s" bulunamadı veya kayıtlı değil (arayüzler config/services.php içinde bağlanmalı).', $id));
        }

        $reflector = new ReflectionClass($id);

        if (!$reflector->isInstantiable()) {
            throw new ContainerException(sprintf('"%s" örneklenemez (soyut sınıf veya arayüz).', $id));
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return $reflector->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->resolve($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContainerException(sprintf(
                '"%s" sınıfının "$%s" parametresi otomatik çözülemiyor; config/services.php içinde bağlayın.',
                $id,
                $parameter->getName()
            ));
        }

        return $reflector->newInstanceArgs($arguments);
    }
}
