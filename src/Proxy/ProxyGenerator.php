<?php

namespace Util\Proxy;

class ProxyGenerator extends AbstractProxyGenerator
{
    private $superClass;
    private $proxyDirectory;
    private $proxyNamespace;
    private $proxyInterfaces;
    private $proxyMethods = [];
    private $proxyClassTemplate = '<?php
    
    namespace <namespace>;

    use Util\Proxy\{
        MethodHandlerInterface,
        ProxyInterface
    };
    
    class <proxyShortClassName> extends <proxySuperClass> implements ProxyInterface<baseProxyInterface>
    {
        private $handler;

        public function setHandler(MethodHandlerInterface $handler): void
        {
            $this->handler = $handler;
        }

        <proxyMethods>
    }
    ';

    public function __construct(string $superClass, array $interfaces = [], string $proxyDirectory = null, string $proxyNamespace = null)
    {
        $ref = new \ReflectionClass($superClass);
        if ($proxyDirectory === null && $proxyNamespace === null) {
            $proxyNamespace = $ref->getNamespaceName();
            $proxyDirectory = dirname($ref->getFileName());
        }
        $this->superClass = $superClass;
        $this->proxyDirectory = $proxyDirectory;
        $this->proxyNamespace = $proxyNamespace;
        $this->proxyInterfaces = $interfaces;

        $methods = $ref->getMethods();
        foreach ($methods as $method) {
            if ($method->isPublic()) {
                $this->proxyMethods[] = $method;
            }
        }
    }

    public function generateProxyClass(): string
    {
        $proxyShortClassName = $this->generateProxyShortClassName();
        $proxyFullClassName = $this->proxyNamespace . '\\' . $proxyShortClassName;

        if (!class_exists($proxyFullClassName)) {
            $replacements = [
                '<namespace>' => $this->generateProxyNamespace(),
                '<proxySuperClass>' => $this->generateSuperClass(),
                '<baseProxyInterface>' => $this->generateBaseProxyInterface(),
                '<proxyShortClassName>' => $proxyShortClassName,
                '<proxyMethods>' => $this->generateProxyMethods()
            ];

            $proxyCode = strtr($this->proxyClassTemplate, $replacements);
            eval(substr($proxyCode, 5));
        }

        return $proxyFullClassName;
    }

    private function generateSuperClass(): string
    {
        $ref = new \ReflectionClass($this->superClass);
        return $ref->getShortName();
    }

    private function generateProxyNamespace(): string
    {
        return $this->proxyNamespace;
    }

    private function generateBaseProxyInterface(): string
    {
        if (!empty($this->proxyInterfaces)) {
            $proxyNamespace = $this->proxyNamespace;
            $cleanInterfaces = array_map(function ($interface) use ($proxyNamespace) {
                return str_replace($proxyNamespace . '\\', '', $interface);
            }, $this->proxyInterfaces);
            return ', ' . implode(', ', $cleanInterfaces);
        }
        return '';
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
            $methodCode .= "\n        {\n";

            $shouldReturn = $this->shouldProxiedMethodReturn($method);

            $methodCode .= '            if ($this->handler === null) {' . "\n";
            $methodCode .= '                ' . ($shouldReturn ? 'return ' : '') . "parent::$methodName(...func_get_args());\n";
            $methodCode .= "            } else {\n";
            $methodCode .= '                $selfRef = new \ReflectionClass($this);' . "\n";
            $methodCode .= '                $superRef = $selfRef->getParentClass();' . "\n";
            $methodCode .= '                ' . ($shouldReturn ? 'return ' : '') . ' $this->handler->invoke($this, $selfRef->getMethod(\'' . $methodName . '\'), $superRef->getMethod(\'' . $methodName . '\'), ' . "func_get_args());\n";
            $methodCode .= "            }\n";
            $methodCode .= "        }\n";
            $methodCodes[] = $methodCode;
        }
        return implode("\n", $methodCodes);
    }
}
