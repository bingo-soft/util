<?php

namespace Tests\Reflection;

use PHPUnit\Framework\TestCase;
use Util\Reflection\MetaObject;

class GenericsTest extends TestCase
{
    public function testTypeErrorThrownWhenStrictTypeCheckingIsSet(): void
    {
        $child = new Child();
        $meta = new MetaObject($child);
        $meta->setStrictTypeChecking();

        $meta->setValue('child', new Child());

        $this->expectException(\TypeError::class);
        $meta->setValue('child.id', 123);
    }

    public function testTypeErrorNotThrownWhenStrictTypeCheckingNotSet(): void
    {
        $child = new Child();
        $meta = new MetaObject($child);

        $meta->setValue('child', new Child());

        $meta->setValue('child.id', 123);
        $this->assertEquals(123, $meta->getValue('child.id'));
    }
}
