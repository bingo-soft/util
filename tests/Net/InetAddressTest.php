<?php

namespace Tests\Net;

use Util\Net\{
    InetAddress,
    Inet4Address
};
use PHPUnit\Framework\TestCase;

class InetAddressTest extends TestCase
{
    public function testGetLocalHost(): void
    {
        $this->assertNotNull(InetAddress::getLocalHost());
    }

    public function testGetByName(): void
    {
        $address = InetAddress::getByName("google.com");
        $this->assertNotNull($address);
        $this->assertNotNull($address->getHostAddress());
    }

    public function testGetHostName(): void
    {
        $address = InetAddress::getByName("64.233.165.100");
        $this->assertEquals("64.233.165.100", $address->getHostName());
    }

    public function testGetAddress(): void
    {
        $addr = [0x7f, 0x00, 0x00, 0x01];
        $address = InetAddress::getByAddress(null, $addr);
        $this->assertEquals($addr, $address->getAddress());
    }

    public function testEquals(): void
    {
        $address1 = InetAddress::getByName("google.com");
        $address2 = InetAddress::getByName("google.com");
        $this->assertEquals($address1, $address2);
    }
}
