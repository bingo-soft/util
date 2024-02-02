<?php

namespace Tests\Net;

use Util\Net\Naming\StringRefAddr;
use PHPUnit\Framework\TestCase;

class StringRefAddrTest extends TestCase
{
    public function testContent(): void
    {
        $ref = new StringRefAddr("url", "jdbc:mysql://localhost:3306/mydatabase");
        $this->assertEquals("url", $ref->getType());
        $this->assertEquals("jdbc:mysql://localhost:3306/mydatabase", $ref->getContent());

        $ref = new StringRefAddr("url");
        $this->assertNull($ref->getContent());
    }
}
