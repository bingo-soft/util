<?php

namespace Util\Proxy;

class ProxyGenerator
{
    private $proxyInterfaces;
    private $proxyDirectory;
    private $proxyNamespace;

    private $proxyMethods = [];

    private $proxyClassTemplate = '<?php
    
    namespace <namespace>;
    
    use Util\Proxy\InvocationHandlerInterface;
    
    class <proxyShortClassName> implements <baseProxyInterface>
    {
        private $handler;
        private $props = [];
    
        public function __construct(InvocationHandlerInterface $handler)
        {
            $this->handler = $handler;
            $refHandler = new \ReflectionObject($this->handler);
            $refProps = $refHandler->getProperties();
            foreach ($refProps as $refProp) {
                if ($refProp->isPrivate()) {
                    $refProp->setAccessible(true);
                }
                $prop = $refProp->getValue($this->handler);
                if (is_object($prop)) {
                    $this->props[$refProp->getName()] = new \ReflectionClass($prop);
                }
            }
        }

        <proxyMethods>
        
    }
    ';

    public function __construct(array $proxyInterfaces, string $proxyDirectory = null, string $proxyNamespace = null)
    {
        $this->proxyInterfaces = $proxyInterfaces;

        if ($proxyDirectory === null && $proxyNamespace === null) {
            foreach ($this->proxyInterfaces as $interface) {
                $refInterface = new \ReflectionClass($interface);

                $namespace = $refInterface->getNamespaceName();
                if ($proxyNamespace == null) {
                    $proxyNamespace = $namespace;
                    $proxyDirectory = dirname($refInterface->getFileName());
                } elseif ($namespace != $proxyNamespace) {
                    throw new \Exception("Interfaces from different packages are not allowed");
                }

                $methods = $refInterface->getMethods();
                $this->proxyMethods = array_merge($this->proxyMethods, $methods);
            }
        }
        $this->proxyDirectory = $proxyDirectory;
        $this->proxyNamespace = $proxyNamespace;
    }

    public function generateProxyClass(): string
    {
        $proxyShortClassName = $this->generateProxyShortClassName();
        $proxyFullClassName = $this->proxyNamespace . '\\' . $proxyShortClassName;

        if (!class_exists($proxyFullClassName)) {
            $replacements = [
                '<namespace>' => $this->generateProxyNamespace(),
                '<baseProxyInterface>' => $this->generateBaseProxyInterface(),
                '<proxyShortClassName>' => $proxyShortClassName,
                '<proxyMethods>' => $this->generateProxyMethods()
            ];

            $proxyCode = strtr($this->proxyClassTemplate, $replacements);

            eval(substr($proxyCode, 5));
        }

        return $proxyFullClassName;
    }

    private function generateProxyNamespace(): string
    {
        return $this->proxyNamespace;
    }

    private function generateBaseProxyInterface(): string
    {
        $proxyNamespace = $this->proxyNamespace;
        $cleanInterfaces = array_map(function ($interface) use ($proxyNamespace) {
            return str_replace($proxyNamespace . '\\', '', $interface);
        }, $this->proxyInterfaces);
        return implode(', ', $cleanInterfaces);
    }

    private function generateProxyShortClassName(): string
    {
        for ($i = 0; $i < PHP_INT_MAX; $i += 1) {
            $proxyShortClassName = 'ProxyClass' . $i;
            if (!class_exists($this->proxyNamespace . '\\' . $proxyShortClassName)) {
                return $proxyShortClassName;
            }
        }
    }

    private function generateProxyMethods(): string
    {
        $methodCodes = [];
        foreach ($this->proxyMethods as $method) {
            $params = $method->getParameters();
            $methodName = $method->getName();
            $methodCode = 'public function ' ;
            if ($method->returnsReference()) {
                $methodCode .= '&';
            }
            $methodCode .= $methodName . '(' . $this->buildParametersString($method->getParameters()) . ')';

            $methodCode .= $this->getMethodReturnType($method);
            $methodCode .= "\n    {\n";

            $shouldReturn = $this->shouldProxiedMethodReturn($method);

            $methodCode .= "\n" . '        foreach ($this->props as $prop) {';
            $methodCode .= "\n" . '            if ($prop->hasMethod(\'' . $methodName . '\')) {';
            $methodCode .= "\n" . '                ' . ($shouldReturn ? 'return ' : '');
            $methodCode .= '$this->handler->invoke($this, $prop->getMethod(\'' . $methodName . '\'), func_get_args());';
            $methodCode .= "\n" . '            }';
            $methodCode .= "\n" . '        }';

            $methodCode .= "\n    }\n";
            $methodCodes[] = $methodCode;
        }
        return implode("\n", $methodCodes);
    }

    private function buildParametersString(array $parameters)
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

    private function getParameterType(\ReflectionParameter $parameter)
    {
        if (!$parameter->hasType()) {
            return null;
        }

        $declaringFunction = $parameter->getDeclaringFunction();

        assert($declaringFunction instanceof \ReflectionMethod);

        return $this->formatType($parameter->getType(), $declaringFunction, $parameter);
    }

    private function getParameterDefaultValue(\ReflectionParameter $parameter)
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

    private function getMethodReturnType(\ReflectionMethod $method)
    {
        if (!$method->hasReturnType()) {
            return '';
        }

        return ': ' . $this->formatType($method->getReturnType(), $method);
    }

    private function shouldProxiedMethodReturn(\ReflectionMethod $method)
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

    private function formatType(
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
