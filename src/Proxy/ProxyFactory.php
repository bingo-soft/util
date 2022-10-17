<?php

namespace Util\Proxy;

class ProxyFactory
{
    private $superClass;

    private $interfaces = [];

    private $handler;

    public function setSuperclass(string $superClass)
    {
        $this->superClass = $superClass;
    }

    public function setInterfaces(array $interfaces): void
    {
        $this->interfaces = $interfaces;
    }

    public function create(array $args = [])
    {
        $proxyGenerator = new ProxyGenerator($this->superClass, $this->interfaces);
        $cons = $proxyGenerator->generateProxyClass();
        return new $cons(...$args);
    }
}
