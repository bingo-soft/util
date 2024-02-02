<?php

namespace Tests\Net;

use Util\Net\Url\Uri;
use Util\Net\NameService\Dns\DnsUrl;
use PHPUnit\Framework\TestCase;

class UriTest extends TestCase
{
    public function testUris(): void
    {
        $uri = new Uri('http://www.example.com/path/to/resource?id=1');
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('www.example.com', $uri->getHost());
        $this->assertEquals(-1, $uri->getPort());
        $this->assertEquals('/path/to/resource', $uri->getPath());
        $this->assertEquals('?id=1', $uri->getQuery());

        $uri = new Uri('http://www.example.com:8181/path/to/resource?id=1');
        $this->assertEquals(8181, $uri->getPort());

        //$uri = new Uri('ftp://user:password@ftp.example.com/path/to/resource');
        //$this->assertEquals('ftp', $uri->getScheme());
        //$this->assertEquals('ftp.example.com', $uri->getHost());
    }

    public function testDns(): void
    {
        $uri = new DnsUrl('dns://example.com');
        $this->assertEquals('dns', $uri->getScheme());
        $this->assertEquals('example.com', $uri->getHost());
        $this->assertEquals('.', $uri->getDomain());

        $uri = new DnsUrl('dns://example.com/foo');
        $this->assertEquals('foo', $uri->getDomain());        
    }
}
