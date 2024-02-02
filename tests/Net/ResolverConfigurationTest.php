<?php

namespace Tests\Net;

use Util\Net\NameService\Dns\ResolverConfigurationImpl;
use PHPUnit\Framework\TestCase;

class ResolverConfigurationTest extends TestCase
{
    public function testMethods(): void
    {
        $conf = new ResolverConfigurationImpl();
        $this->assertNotEmpty($conf->searchlist());
        $this->assertNotEmpty($conf->nameservers());
    }
}
