<?php

namespace Tests\Net;

use Util\Net\InetAddressCachePolicy;
use PHPUnit\Framework\TestCase;

class InetAddressCachePolicyTest extends TestCase
{
    public function testInetAddressCachePolicy(): void
    {
        $cp = new InetAddressCachePolicy();
        $this->assertEquals(30, $cp->get());
        $this->assertEquals(10, $cp->getNegative());
    }
}
