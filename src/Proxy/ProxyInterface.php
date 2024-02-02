<?php

namespace Util\Proxy;

interface ProxyInterface
{
    public function setHandler(MethodHandlerInterface $handler): void;
}
