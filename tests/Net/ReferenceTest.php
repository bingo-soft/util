<?php

namespace Tests\Net;

use Util\Net\Naming\{
    Reference,
    StringRefAddr
};
use PHPUnit\Framework\TestCase;

class ReferenceTest extends TestCase
{
    public function testMethods(): void
    {
        $ref = new Reference("MyBatis\DataSource\DataSourceInterface", "UnpooledDataSourceFactory", "MyBatis\DataSource\Unpooled\UnpooledDataSourceFactory");
        $ref->add(new StringRefAddr("driver", "pdo_pgsql"));
        $ref->add(new StringRefAddr("url", null));
        $ref->add(new StringRefAddr("host", "127.0.0.1"));
        $ref->add(new StringRefAddr("port", 5432));
        $ref->add(new StringRefAddr("username", "postgres"));
        $ref->add(new StringRefAddr("password", "postgres"));
        
        $this->assertEquals("MyBatis\DataSource\DataSourceInterface", $ref->getClassName());
        $this->assertEquals("UnpooledDataSourceFactory", $ref->getFactoryClassName());
        $this->assertEquals("MyBatis\DataSource\Unpooled\UnpooledDataSourceFactory", $ref->getFactoryClassLocation());

        $this->assertEquals(6, count($ref->getAll()));
        $ref->remove(1);
        $this->assertEquals(5, count($ref->getAll()));
        $this->assertEquals("127.0.0.1", $ref->get(1)->getContent());

        $ref->add(1, [ new StringRefAddr("url", null) ]);
        $this->assertEquals(6, count($ref->getAll()));
        $this->assertNull($ref->get(1)->getContent());
    }
}
