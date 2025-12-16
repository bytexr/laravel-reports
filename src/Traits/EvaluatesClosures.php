<?php

declare(strict_types=1);

namespace ByteXR\DynamicReporter\Traits;

use ByteXR\DynamicReporter\Exceptions\ClosureSecurityException;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

trait EvaluatesClosures
{
    /**
     * Allowlisted classes that can be injected into closures.
     * This prevents closures from accessing dangerous services like Process, File, etc.
     *
     * @var array<int, class-string>
     */
    protected static array $allowlistedClasses = [
        Authenticatable::class,
        AuthFactory::class,
        Guard::class,
        Gate::class,
        Request::class,
        \Illuminate\Contracts\Config\Repository::class,
        \Illuminate\Contracts\Cache\Repository::class,
        \Illuminate\Contracts\Translation\Translator::class,
        \Illuminate\Contracts\Routing\UrlGenerator::class,
        \Psr\Log\LoggerInterface::class,
    ];

    /**
     * Blocklisted classes that should never be injected into closures.
     * These are dangerous classes that could be used for arbitrary code execution.
     *
     * @var array<int, string>
     */
    protected static array $blocklistedClasses = [
        'Illuminate\Filesystem\Filesystem',
        'Illuminate\Contracts\Filesystem\Filesystem',
        'Illuminate\Process\Factory',
        'Illuminate\Process\PendingProcess',
        'Symfony\Component\Process\Process',
        'Illuminate\Console\Command',
        'Illuminate\Foundation\Console\Kernel',
        'Illuminate\Database\Connection',
        'Illuminate\Database\DatabaseManager',
        'PDO',
    ];

    /**
     * Blocklisted function patterns that should not be called in closures.
     *
     * @var array<int, string>
     */
    protected static array $blocklistedFunctions = [
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'popen',
        'proc_open',
        'pcntl_exec',
        'eval',
        'assert',
        'create_function',
        'call_user_func',
        'call_user_func_array',
        'file_get_contents',
        'file_put_contents',
        'fopen',
        'fwrite',
        'unlink',
        'rmdir',
        'mkdir',
        'rename',
        'copy',
        'move_uploaded_file',
    ];

    /**
     * Evaluate a value, resolving closures with dependency injection.
     *
     * @template T
     * @param T|Closure(mixed...): T $value
     * @param array<string, mixed> $namedInjections Named parameters to inject
     * @param array<class-string, mixed> $typedInjections Typed parameters to inject
     * @return T
     * @throws ClosureSecurityException
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
     * @throws ClosureSecurityException
     */
    protected function evaluateClosure(
        Closure $closure,
        array $namedInjections = [],
        array $typedInjections = [],
    ): mixed {
        $this->validateClosureSafety($closure);

        $parameters = $this->resolveClosureParameters($closure, $namedInjections, $typedInjections);

        return $closure(...$parameters);
    }

    /**
     * Validate that a closure doesn't contain dangerous operations.
     *
     * @throws ClosureSecurityException
     */
    protected function validateClosureSafety(Closure $closure): void
    {
        $reflection = new ReflectionFunction($closure);

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $typeName = $type->getName();

                if ($this->isBlocklistedClass($typeName)) {
                    throw new ClosureSecurityException(
                        "Closure requests blocklisted class [{$typeName}]. This class is not allowed for security reasons."
                    );
                }
            }
        }

        $fileName = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if ($fileName !== false && $startLine !== false && $endLine !== false) {
            $this->scanClosureForDangerousCalls($fileName, $startLine, $endLine);
        }
    }

    /**
     * Scan closure source code for dangerous function calls.
     *
     * @throws ClosureSecurityException
     */
    protected function scanClosureForDangerousCalls(string $fileName, int $startLine, int $endLine): void
    {
        if (! file_exists($fileName) || ! is_readable($fileName)) {
            return;
        }

        $lines = file($fileName);

        if ($lines === false) {
            return;
        }

        $closureCode = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        foreach (self::$blocklistedFunctions as $function) {
            $pattern = '/\b' . preg_quote($function, '/') . '\s*\(/i';

            if (preg_match($pattern, $closureCode)) {
                throw new ClosureSecurityException(
                    "Closure contains blocklisted function call [{$function}]. This function is not allowed for security reasons."
                );
            }
        }
    }

    /**
     * Check if a class is blocklisted.
     */
    protected function isBlocklistedClass(string $className): bool
    {
        foreach (self::$blocklistedClasses as $blocklisted) {
            if ($className === $blocklisted || is_subclass_of($className, $blocklisted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a class is allowlisted for injection.
     */
    protected function isAllowlistedClass(string $className): bool
    {
        foreach (self::$allowlistedClasses as $allowlisted) {
            if ($className === $allowlisted || is_subclass_of($className, $allowlisted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the parameters for a closure using dependency injection.
     *
     * @param array<string, mixed> $namedInjections
     * @param array<class-string, mixed> $typedInjections
     * @return array<int, mixed>
     * @throws ClosureSecurityException
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
     * @throws ClosureSecurityException
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

            return $this->resolveFromContainerSafely($typeName);
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
     * Resolve a class from the container with security checks.
     *
     * @param class-string $class
     * @throws ClosureSecurityException
     */
    protected function resolveFromContainerSafely(string $class): mixed
    {
        if ($this->isBlocklistedClass($class)) {
            throw new ClosureSecurityException(
                "Cannot inject blocklisted class [{$class}] into closure."
            );
        }

        if (! $this->isAllowlistedClass($class)) {
            if (! class_exists($class) && ! interface_exists($class)) {
                return null;
            }

            $isUserModel = is_subclass_of($class, Authenticatable::class);

            if (! $isUserModel) {
                throw new ClosureSecurityException(
                    "Class [{$class}] is not in the allowlist for closure injection. " .
                    "Only safe services like Auth, Request, Config, Cache, and Translator are allowed."
                );
            }
        }

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

    /**
     * Add a class to the allowlist (useful for extending in applications).
     *
     * @param class-string $class
     */
    public static function allowClass(string $class): void
    {
        if (! in_array($class, self::$allowlistedClasses, true)) {
            self::$allowlistedClasses[] = $class;
        }
    }

    /**
     * Add a class to the blocklist.
     */
    public static function blockClass(string $class): void
    {
        if (! in_array($class, self::$blocklistedClasses, true)) {
            self::$blocklistedClasses[] = $class;
        }
    }
}
