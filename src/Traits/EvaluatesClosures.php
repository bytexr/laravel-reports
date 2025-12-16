<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Traits;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\App;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

trait EvaluatesClosures
{
    /**
     * Evaluate a value, resolving closures with dependency injection.
     *
     * @template T
     * @param T|Closure(mixed...): T $value
     * @param array<string, mixed> $namedInjections Named parameters to inject
     * @param array<class-string, mixed> $typedInjections Typed parameters to inject
     * @return T
     */
    public function evaluate(
        mixed $value,
        array $namedInjections = [],
        array $typedInjections = [],
    ): mixed {
        if (! $value instanceof Closure) {
            return $value;
        }

        return $this->evaluateClosure($value, $namedInjections, $typedInjections);
    }

    /**
     * Evaluate a closure with dependency injection.
     *
     * @param array<string, mixed> $namedInjections
     * @param array<class-string, mixed> $typedInjections
     */
    protected function evaluateClosure(
        Closure $closure,
        array $namedInjections = [],
        array $typedInjections = [],
    ): mixed {
        $parameters = $this->resolveClosureParameters($closure, $namedInjections, $typedInjections);

        return $closure(...$parameters);
    }

    /**
     * Resolve the parameters for a closure using dependency injection.
     *
     * @param array<string, mixed> $namedInjections
     * @param array<class-string, mixed> $typedInjections
     * @return array<int, mixed>
     */
    protected function resolveClosureParameters(
        Closure $closure,
        array $namedInjections = [],
        array $typedInjections = [],
    ): array {
        $reflection = new ReflectionFunction($closure);
        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameters[] = $this->resolveParameter($parameter, $namedInjections, $typedInjections);
        }

        return $parameters;
    }

    /**
     * Resolve a single parameter value.
     *
     * @param array<string, mixed> $namedInjections
     * @param array<class-string, mixed> $typedInjections
     */
    protected function resolveParameter(
        ReflectionParameter $parameter,
        array $namedInjections = [],
        array $typedInjections = [],
    ): mixed {
        $parameterName = $parameter->getName();

        if (array_key_exists($parameterName, $namedInjections)) {
            return $namedInjections[$parameterName];
        }

        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $typeName = $type->getName();

            if (array_key_exists($typeName, $typedInjections)) {
                return $typedInjections[$typeName];
            }

            return $this->resolveFromContainer($typeName);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new \InvalidArgumentException(
            "Unable to resolve parameter [{$parameterName}] for closure evaluation."
        );
    }

    /**
     * Resolve a class from the container.
     *
     * @param class-string $class
     */
    protected function resolveFromContainer(string $class): mixed
    {
        $container = $this->getContainer();

        if ($container->bound($class) || class_exists($class)) {
            return $container->make($class);
        }

        return null;
    }

    /**
     * Get the container instance.
     */
    protected function getContainer(): Container
    {
        return App::getInstance();
    }
}
