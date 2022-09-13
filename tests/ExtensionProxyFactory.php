<?php

namespace Tests;

use Util\Proxy\{
    MethodHandlerInterface,
    ProxyFactory
};

class ExtensionProxyFactory
{
    public static function createProxy(string $type, MethodHandlerInterface $method, array $args = [])
    {
        $enhancer = new ProxyFactory();
        $enhancer->setSuperclass($type);
        $enhancer->setInterfaces([BusinessServiceInterface::class]);
        return $enhancer->create($args);
    }
}