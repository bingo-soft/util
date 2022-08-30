<?php

namespace Tests;

use Util\Proxy\Proxy;
use PHPUnit\Framework\TestCase;

class ProxyTest extends TestCase
{
    public function testProxy(): void
    {
        $original = new Original();
        $handler = new Handler($original);
        $proxy = Proxy::newProxyInstance([ InterInterface::class, SecondInterface::class ], $handler);
        $res = $proxy->originalMethod('Hello');
        $this->assertTrue($proxy instanceof InterInterface);
        $this->assertTrue($proxy instanceof SecondInterface);
        $this->assertCount(3, $res);
        $this->assertEquals('Before', $res[0]);
        $this->assertEquals('Hello', $res[1]);
        $this->assertEquals('After', $res[2]);
    }
}
