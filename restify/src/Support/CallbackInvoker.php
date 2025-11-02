<?php

declare(strict_types=1);

namespace Restify\Support;

use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use Restify\Http\Request;

final class CallbackInvoker
{
    public static function invoke(callable $callable, Request $request, array $parameters): mixed
    {
        $reflection = self::reflect($callable);
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();

                if ($typeName === Request::class) {
                    $arguments[] = $request;
                    continue;
                }

                if ($typeName === 'array') {
                    $arguments[] = $parameters;
                    continue;
                }

                if ($type->isBuiltin() && array_key_exists($parameter->getName(), $parameters)) {
                    $arguments[] = self::cast($parameters[$parameter->getName()], $typeName);
                    continue;
                }
            }

            $name = $parameter->getName();

            if ($name === 'request') {
                $arguments[] = $request;
                continue;
            }

            if ($name === 'params' || $name === 'parameters') {
                $arguments[] = $parameters;
                continue;
            }

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            $arguments[] = null;
        }

        return call_user_func_array($callable, $arguments);
    }

    private static function reflect(callable $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_string($callable)) {
            if (str_contains($callable, '::')) {
                return new ReflectionMethod($callable);
            }

            return new ReflectionFunction($callable);
        }

        if ($callable instanceof \Closure) {
            return new ReflectionFunction($callable);
        }

        if (is_object($callable) && method_exists($callable, '__invoke')) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction(\Closure::fromCallable($callable));
    }

    private static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            default => $value,
        };
    }
}
