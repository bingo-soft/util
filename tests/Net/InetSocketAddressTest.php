<?php

namespace Tests\Net;

use Util\Net\{
    InetAddress,
    InetSocketAddress
};
use PHPUnit\Framework\TestCase;

class InetSocketAddressTest extends TestCase
{
    public function testInitialization(): void
    {
        $address = InetAddress::getLoopbackAddress();
        $port = 8080;
        $socketAddress = new InetSocketAddress($address, $port);
        
        $this->assertNotNull($socketAddress);
        $this->assertEquals($address, $socketAddress->getAddress());
        $this->assertEquals($port, $socketAddress->getPort());
    }

    public function testConstructorWithHostnameAndPort(): void
    {
        $hostname = "localhost";
        $port = 8080;
        $socketAddress = new InetSocketAddress($hostname, $port);
        
        $this->assertNotNull($socketAddress);
        $this->assertEquals($hostname, $socketAddress->getHostName());
        $this->assertEquals($port, $socketAddress->getPort());
    }

    public function testConstructorWithHostnameAndUnresolvedPort(): void
    {
        $hostname = "unknownhost";
        $port = 49890;
        $socketAddress = new InetSocketAddress($hostname, $port);
        
        $this->assertNotNull($socketAddress);
        $this->assertEquals($hostname, $socketAddress->getHostName());
        $this->assertEquals($port, $socketAddress->getPort());
        $this->assertEquals(true, $socketAddress->isUnresolved());      
    }
}
