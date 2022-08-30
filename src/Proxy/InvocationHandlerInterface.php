<?php

namespace Util\Proxy;

interface InvocationHandlerInterface
{
    public function invoke($proxy, \ReflectionMethod $method, array $args);
}
