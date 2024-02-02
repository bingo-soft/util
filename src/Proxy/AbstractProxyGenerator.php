<?php

namespace Util\Proxy;

abstract class AbstractProxyGenerator
{
    protected function buildParametersString(array $parameters)
    {
        $parameterDefinitions = [];

        $i = -1;
        foreach ($parameters as $param) {
            assert($param instanceof \ReflectionParameter);
            $i++;
            $parameterDefinition = '';
            $parameterType       = $this->getParameterType($param);

            if ($parameterType !== null) {
                $parameterDefinition .= $parameterType . ' ';
            }

            if ($param->isPassedByReference()) {
                $parameterDefinition .= '&';
            }

            if ($param->isVariadic()) {
                $parameterDefinition .= '...';
            }

            $parameterDefinition .= '$' . $param->getName();
            $parameterDefinition .= $this->getParameterDefaultValue($param);

            $parameterDefinitions[] = $parameterDefinition;
        }

        return implode(', ', $parameterDefinitions);
    }

    protected function getParameterType(\ReflectionParameter $parameter)
    {
        if (!$parameter->hasType()) {
            return null;
        }

        $declaringFunction = $parameter->getDeclaringFunction();

        assert($declaringFunction instanceof \ReflectionMethod);

        return $this->formatType($parameter->getType(), $declaringFunction, $parameter);
    }

    protected function getParameterDefaultValue(\ReflectionParameter $parameter)
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return '';
        }

        if (PHP_VERSION_ID < 80100) {
            return ' = ' . var_export($parameter->getDefaultValue(), true);
        }

        $value = rtrim(substr(explode('$' . $parameter->getName() . ' = ', (string) $parameter, 2)[1], 0, -2));

        return ' = ' . $value;
    }

    protected function getMethodReturnType(\ReflectionMethod $method)
    {
        if (!$method->hasReturnType()) {
            return '';
        }

        return ': ' . $this->formatType($method->getReturnType(), $method);
    }

    protected function shouldProxiedMethodReturn(\ReflectionMethod $method)
    {
        if (!$method->hasReturnType()) {
            return true;
        }

        return !in_array(
            strtolower($this->formatType($method->getReturnType(), $method)),
            ['void', 'never'],
            true
        );
    }

    protected function formatType(
        \ReflectionType $type,
        \ReflectionMethod $method,
        ?\ReflectionParameter $parameter = null
    ) {
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(
                function (\ReflectionType $unionedType) use ($method, $parameter) {
                    return $this->formatType($unionedType, $method, $parameter);
                },
                $type->getTypes()
            ));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return implode('&', array_map(
                function (\ReflectionType $intersectedType) use ($method, $parameter) {
                    return $this->formatType($intersectedType, $method, $parameter);
                },
                $type->getTypes()
            ));
        }

        assert($type instanceof \ReflectionNamedType);

        $name      = $type->getName();
        $nameLower = strtolower($name);

        if ($nameLower === 'static') {
            $name = 'static';
        }

        if ($nameLower === 'self') {
            $name = $method->getDeclaringClass()->getName();
        }

        if ($nameLower === 'parent') {
            $name = $method->getDeclaringClass()->getParentClass()->getName();
        }

        if (! $type->isBuiltin() && ! class_exists($name) && ! interface_exists($name) && $name !== 'static') {
            if ($parameter !== null) {
                throw UnexpectedValueException::invalidParameterTypeHint(
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $parameter->getName()
                );
            }

            throw UnexpectedValueException::invalidReturnTypeHint(
                $method->getDeclaringClass()->getName(),
                $method->getName()
            );
        }

        if (! $type->isBuiltin() && $name !== 'static') {
            $name = '\\' . $name;
        }

        if (
            $type->allowsNull()
            && ! in_array($name, ['mixed', 'null'], true)
            && ($parameter === null || ! $parameter->isDefaultValueAvailable() || $parameter->getDefaultValue() !== null)
        ) {
            $name = '?' . $name;
        }

        return $name;
    }
}
