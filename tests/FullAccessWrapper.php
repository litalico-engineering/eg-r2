<?php
declare(strict_types=1);

namespace Tests;

use ErrorException;
use ReflectionClass;
use ReflectionException;

/**
 * Class for testing private, protected methods and properties
 */
class FullAccessWrapper
{
    /** @var ReflectionClass reflection */
    private readonly ReflectionClass $reflection;

    /**
     * @param object $targetInstance
     */
    public function __construct(private readonly object $targetInstance)
    {
        $this->reflection = new ReflectionClass($targetInstance);
    }

    /**
     * @param string $methodName
     * @param array<int,mixed> $args
     * @return mixed
     * @throws ReflectionException
     */
    public function __call(string $methodName, array $args)
    {
        $method = $this->reflection->getMethod($methodName);

        return $method->invokeArgs($this->targetInstance, $args);
    }

    /**
     * @param string $name
     * @return mixed|void
     * @throws ReflectionException
     * @throws ErrorException
     */
    public function __get(string $name)
    {
        if ($this->reflection->hasProperty($name)) {
            $property = $this->reflection->getProperty($name);

            return $property->getValue($this->targetInstance);
        }

        throw new ErrorException("ErrorException : Undefined property: $name");
    }

    /**
     * @param string $name
     * @return void
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->reflection->hasProperty($name)) {
            $property = $this->reflection->getProperty($name);

            $property->setValue($this->targetInstance, $value);
        } else {
            throw new ErrorException("ErrorException : Undefined property: $name");
        }
    }

    /**
     * Get reflection source instance
     * @return Object
     */
    public function getInstance(): Object
    {
        return $this->targetInstance;
    }
}
