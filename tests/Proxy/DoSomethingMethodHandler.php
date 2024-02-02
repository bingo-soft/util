<?php

namespace Tests\Proxy;

use Util\Proxy\{
    MethodHandlerInterface,
    ProxyInterface
};

class DoSomethingMethodHandler implements MethodHandlerInterface
{
    public function invoke($proxy, \ReflectionMethod $thisMethod, \ReflectionMethod $proceed, array $args)
    {
        return 'prepend - ' . $proceed->invoke($proxy, ...$args);
    }
}