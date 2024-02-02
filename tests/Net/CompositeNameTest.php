<?php

namespace Tests\Net;

use Util\Net\Naming\CompositeName;
use PHPUnit\Framework\TestCase;

class CompositeNameTest extends TestCase
{
    public function testMethods(): void
    {
        $cn = new CompositeName("root/branch/leaf");
        $this->assertEquals(["root", "branch", "leaf"], $cn->getAll());
        $this->assertEquals("root", $cn->get(0));
        $this->assertEquals("branch", $cn->get(1));
        $this->assertEquals("leaf", $cn->get(2));
        $this->assertNull($cn->get(3));
        $this->assertNull($cn->get(-1));

        $cn->add("boo");
        $this->assertEquals("boo", $cn->get(3));
        $cn->addAll(["bar", "zoo"]);
        $this->assertEquals("bar", $cn->get(4));
        $this->assertEquals("zoo", $cn->get(5));

        $cn->remove(0);
        $this->assertEquals("branch", $cn->get(0));

        $this->assertTrue($cn->startsWith(new CompositeName("branch/leaf")));
        $this->assertFalse($cn->startsWith(new CompositeName("branch/")));
        $this->assertTrue($cn->startsWith(new CompositeName("branch")));

        $this->assertTrue($cn->equals(new CompositeName("branch/leaf/boo/bar/zoo")));
        $this->assertFalse($cn->equals(new CompositeName("branch/leaf/boo/bar")));
        $this->assertEquals(1, $cn->compareTo(new CompositeName("branch/leaf/boo/bar")));
        $this->assertEquals(0, $cn->compareTo(new CompositeName("branch/leaf/boo/bar/zoo")));
        $this->assertEquals(-1, $cn->compareTo(new CompositeName("branch/leaf/boo/bar/zoo/goo")));
        $this->assertEquals(-3, $cn->compareTo(new CompositeName("branch/leaf/boo/bar/zoo/goo/dum/dee")));

        $cn = new CompositeName("x/y/");
        $this->assertEquals(["x", "y", ""], $cn->getAll());

        $cn = new CompositeName("x");
        $this->assertEquals(["x"], $cn->getAll());

        $cn = new CompositeName("/x");
        $this->assertEquals(["", "x"], $cn->getAll());
    }
}
