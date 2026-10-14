<?php
namespace App\Core;

use Closure;
use Exception;

class Container 
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, Closure $concrete): void 
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function get(string $abstract) 
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            // Eğer binding tanımlanmamışsa sınıfıReflection ile otomatik üretmeyi deneyebiliriz
            return $this->resolveAutomatic($abstract);
        }

        $concrete = $this->bindings[$abstract];
        $object = $concrete($this);

        return $object;
    }

    private function resolveAutomatic(string $target) 
    {
        $reflector = new \ReflectionClass($target);
        
        if (!$reflector->isInstantiable()) {
            throw new Exception("Sınıf örneklenemez: {$target}");
        }

        $constructor = $reflector->getConstructor();

        if (null === $constructor) {
            return new $target;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencyType = $parameter->getType();
            
            if (!$dependencyType) {
                throw new Exception("{$target} sınıfının bağımlılık tipi belirtilmemiş.");
            }

            $dependencies[] = $this->get($dependencyType->getName());
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}