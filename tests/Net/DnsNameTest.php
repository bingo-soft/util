<?php

namespace Tests\Net;

use Util\Net\NameService\Dns\DnsName;
use PHPUnit\Framework\TestCase;

class DnsNameTest extends TestCase
{
    public function testMethods(): void
    {
        $n = new DnsName("mail.example.com");
        $this->assertEquals("mail.example.com", strval($n));

        $this->assertTrue($n->isHostName());
        $this->assertFalse((new DnsName("invalid_host"))->isHostName());

        $this->assertEquals(3, $n->size());

        $n->add("super");
        $this->assertEquals("super.mail.example.com", strval($n));

        $n->add(0, "duper");
        $this->assertEquals("super.mail.example.com.duper", strval($n));

        $n->addAll(1, new DnsName("foo.bar.baz"));
        $this->assertEquals("super.mail.example.com.foo.bar.baz.duper", strval($n));

        $n->add(0, "");
        $this->assertTrue($n->hasRootLabel());

        $this->assertEquals("", $n->getKey(0));
        $this->assertEquals("duper", $n->getKey(1));
        $this->assertEquals("super", $n->getKey(8));

        $n->remove(0);
        $this->assertEquals("super.mail.example.com.foo.bar.baz.duper", strval($n));
        $n->remove(7);
        $this->assertEquals("mail.example.com.foo.bar.baz.duper", strval($n));
        $n->remove(1);
        $this->assertEquals("mail.example.com.foo.bar.duper", strval($n));
    }
}
