<?php

namespace Tests\Reflection;

use PHPUnit\Framework\TestCase;
use Tests\Domain\Misc\RichType;
use Tests\Reflection\Beans\{
    BeanClass1,
    BeanClass2
};
use Util\Reflection\{
    DefaultReflectorFactory
};

class ReflectorTest extends TestCase
{
    public function testGetSetterType(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(Section::class);
        $this->assertEquals("int", $reflector->getSetterType("id"));
    }

    public function testGetGetterType(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(Section::class);
        $this->assertEquals("int", $reflector->getGetterType("id"));
    }

    public function testShouldNotGetClass(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(Section::class);
        $this->assertFalse($reflector->hasGetter("class"));
    }

    public function testShouldResolveParameterizedSetterParam(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(Child::class);
        $this->assertEquals("array", $reflector->getSetterType("list"));
    }

    public function testShouldResolveArraySetterParam(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(Child::class);
        $this->assertEquals("array", $reflector->getSetterType("array"));
    }

    public function testShouldResolveGetterType(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(Child::class);
        $this->assertEquals("string", $reflector->getSetterType("id"));
    }

    public function testResolveSetterTypeFromPrivateField(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(Child::class);
        $this->assertEquals("string", $reflector->getSetterType("fld"));
    }

    public function testShouldResolveReadonlySetterWithOverload(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(BeanClass1::class);
        $this->assertEquals("string", $reflector->getSetterType("id"));
    }

    public function testShouldSettersWithUnrelatedArgTypesNotThrowException(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(BeanClass2::class);
     
        $setableProps = $reflector->getSetablePropertyNames();
        $this->assertEquals(["prop1", "prop2"], $setableProps);

        $this->assertEquals("string", $reflector->getSetterType("prop1"));
        $this->assertNotNull($reflector->getSetInvoker("prop1"));
        $this->assertEquals(["string", "int", "bool"], $reflector->getSetterType("prop2"));
        $ambiguousInvoker = $reflector->getSetInvoker("prop2");
        $ambiguousInvoker->invoke(new BeanClass2(), [ 1 ]);
    }

    public function testBooleanField(): void
    {
        $reflectorFactory = new DefaultReflectorFactory();
        $reflector = $reflectorFactory->findForClass(RichType::class);     
        $this->assertEquals("bool", $reflector->getGetterType("withoutDueDate"));
    }
}
