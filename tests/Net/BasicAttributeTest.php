<?php

namespace Tests\Net;

use Util\Net\Naming\Directory\{
    BasicAttribute,
    BasicAttributes
};
use PHPUnit\Framework\TestCase;

class BasicAttributeTest extends TestCase
{
    public function testMethods(): void
    {
        $attribute = new BasicAttribute("email", "example@example.com");
        $this->assertEquals('email', $attribute->getID());
        $this->assertEquals(1, $attribute->size());
        $this->assertEquals('example@example.com', $attribute->get());
        $this->assertFalse($attribute->isOrdered());
        $this->assertEquals('example@example.com', $attribute->get(0));
        $this->assertEquals('example@example.com', $attribute->get(2));

        $attribute->add("foo");
        $this->assertEquals('foo', $attribute->get(1));

        $attribute2 = $attribute->clone();
        $this->assertEquals('foo', $attribute2->get(1));
        $this->assertTrue($attribute->equals($attribute2));

        $this->assertEquals('email: example@example.com, foo', strval($attribute));

        $attribute->remove("example@example.com");
        $this->assertEquals('foo', $attribute->get());
        $this->assertEquals(1, $attribute->size());
        $this->assertEquals('foo', $attribute->get(0));
        $this->assertFalse($attribute->equals($attribute2));

        $attribute2->remove("foo");
        $attribute2->remove("zoo");
        $this->assertEquals(1, $attribute2->size());
        $attribute2->remove("example@example.com");
        $this->assertEquals('email: No values', strval($attribute2));

        $attrs = new BasicAttributes(true);
        $attrs->put($attribute);
        $attrs->put("bar", "baz");
        $this->assertEquals(2, $attrs->size());
        $attrs->remove("email");
        $this->assertEquals(1, $attrs->size());
        $this->assertEquals('bar: baz', $attrs->get("bar"));
    }
}
