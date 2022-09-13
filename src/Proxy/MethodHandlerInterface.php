<?php

namespace Util\Proxy;

interface MethodHandlerInterface
{
    public function invoke($proxy, \ReflectionMethod $thisMethod, \ReflectionMethod $proceed, array $args);
}
