<?php

namespace Tests;

use Util\Proxy\Proxy;
use PHPUnit\Framework\TestCase;

class ProxyTest extends TestCase
{
    public function testProxy(): void
    {
        $method = new DoSomethingMethodHandler();
        $proxy = MyProxyFactory::createProxy(BusinessServiceImpl::class, $method);
        $this->assertEquals('1 - curitis', $proxy->doSomething(1, "curitis"));
        $proxy->setHandler($method);
        $this->assertEquals('prepend - 1 - curitis', $proxy->doSomething(1, "curitis"));
    }
}
