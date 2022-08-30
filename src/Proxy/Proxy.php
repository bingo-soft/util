<?php

namespace Util\Proxy;

class Proxy
{
    public static function newProxyInstance(array $interfaces, InvocationHandlerInterface $h)
    {
        $proxyGenerator = new ProxyGenerator($interfaces);
        $cons = $proxyGenerator->generateProxyClass();
        return new $cons($h);
    }
}
