<?php

namespace Tests;

use Util\Proxy\InvocationHandlerInterface;

class Handler implements InvocationHandlerInterface
{
    private $original;

    public function __construct($original)
    {
        $this->original = $original;
    }

    public function invoke($proxy, \ReflectionMethod $method, array $args)
    {
        return ["Before" , $method->invoke($this->original, ...$args), "After"];
    }
}
