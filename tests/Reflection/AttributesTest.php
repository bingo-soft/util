<?php

namespace Tests\Reflection;

use PHPUnit\Framework\TestCase;
use Util\Reflection\MetaObject;

class AttributesTest extends TestCase
{
    public function testTypeErrorNotThrownWhenStrictTypeCheckingNotSet(): void
    {
        $child = new Child();
        $meta = new MetaObject($child);

        $meta->setValue('child', new Child());

        $meta->setValue('child.id', 123);
        $this->assertEquals(123, $meta->getValue('child.id'));
    }
}
