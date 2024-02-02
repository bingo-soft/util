<?php

namespace Tests\Net;

use Util\Net\HostAndPort;
use PHPUnit\Framework\TestCase;

class HostAndPortTest extends TestCase
{
    public function testFromStringWellFormed(): void
    {
        // Well-formed inputs.
        $this->checkFromStringCase("google.com", 80, "google.com", 80, false);
        $this->checkFromStringCase("google.com", 80, "google.com", 80, false);
        $this->checkFromStringCase("192.0.2.1", 82, "192.0.2.1", 82, false);
        $this->checkFromStringCase("[2001::1]", 84, "2001::1", 84, false);
        $this->checkFromStringCase("2001::3", 86, "2001::3", 86, false);
        $this->checkFromStringCase("host:", 80, "host", 80, false);
    }
    
    public function testFromStringBadDefaultPort(): void
    {
        // Well-formed strings with bad default ports.
        $this->checkFromStringCase("gmail.com:81", -1, "gmail.com", 81, true);
        $this->checkFromStringCase("192.0.2.2:83", -1, "192.0.2.2", 83, true);
        $this->checkFromStringCase("[2001::2]:85", -1, "2001::2", 85, true);
        $this->checkFromStringCase("goo.gl:65535", 65536, "goo.gl", 65535, true);
        // No port, bad default.
        $this->checkFromStringCase("google.com", -1, "google.com", -1, false);
        $this->checkFromStringCase("192.0.2.1", 65536, "192.0.2.1", -1, false);
        $this->checkFromStringCase("[2001::1]", -1, "2001::1", -1, false);
        $this->checkFromStringCase("2001::3", 65536, "2001::3", -1, false);
    }

    public function testFromStringUnusedDefaultPort(): void
    {
        // Default port, but unused.
        $this->checkFromStringCase("gmail.com:81", 77, "gmail.com", 81, true);
        $this->checkFromStringCase("192.0.2.2:83", 77, "192.0.2.2", 83, true);
        $this->checkFromStringCase("[2001::2]:85", 77, "2001::2", 85, true);
    }

    public function testFromStringNonAsciiDigits(): void
    {
        // Same as testFromStringUnusedDefaultPort but with Gujarati digits for port numbers.
        $this->checkFromStringCase("gmail.com:૮1", 77, null, -1, false);
        $this->checkFromStringCase("192.0.2.2:૮૩", 77, null, -1, false);
        $this->checkFromStringCase("[2001::2]:૮૫", 77, null, -1, false);
    }

    public function testFromStringBadPort(): void
    {
        // Out-of-range ports.
        $this->checkFromStringCase("google.com:65536", 1, null, 99, false);
        $this->checkFromStringCase("google.com:9999999999", 1, null, 99, false);
        // Invalid port parts.
        $this->checkFromStringCase("google.com:port", 1, null, 99, false);
        $this->checkFromStringCase("google.com:-25", 1, null, 99, false);
        $this->checkFromStringCase("google.com:+25", 1, null, 99, false);
        $this->checkFromStringCase("google.com:25  ", 1, null, 99, false);
        $this->checkFromStringCase("google.com:25\t", 1, null, 99, false);
        $this->checkFromStringCase("google.com:0x25 ", 1, null, 99, false);
    }

    public function testFromStringUnparseableNonsense(): void
    {
        // Some nonsense that causes parse failures.
        $this->checkFromStringCase("[goo.gl]", 1, null, 99, false);
        $this->checkFromStringCase("[goo.gl]:80", 1, null, 99, false);
        $this->checkFromStringCase("[", 1, null, 99, false);
        $this->checkFromStringCase("[]:", 1, null, 99, false);
        $this->checkFromStringCase("[]:80", 1, null, 99, false);
        $this->checkFromStringCase("[]bad", 1, null, 99, false);
    }

    public function testFromStringParseableNonsense(): void
    {
        // Examples of nonsense that gets through.
        $this->checkFromStringCase("[[:]]", 86, "[:]", 86, false);
        $this->checkFromStringCase("x:y:z", 87, "x:y:z", 87, false);
        $this->checkFromStringCase("", 88, "", 88, false);
        $this->checkFromStringCase(":", 99, "", 99, false);
        $this->checkFromStringCase(":123", -1, "", 123, true);
        $this->checkFromStringCase("\nOMG\t", 89, "\nOMG\t", 89, false);
    }

    public function testFromStringParseableIncompleteAddresses(): void
    {
        $this->checkFromStringCase("1.2.3", 87, "1.2.3", 87, false);
        $this->checkFromStringCase("1.2.3:99", 87, "1.2.3", 99, true);
        $this->checkFromStringCase("2001:4860:4864:5", 87, "2001:4860:4864:5", 87, false);
        $this->checkFromStringCase("[2001:4860:4864:5]:99", 87, "2001:4860:4864:5", 99, true);
    }

    private function checkFromStringCase(
        string $hpString,
        int $defaultPort,
        ?string $expectHost,
        int $expectPort,
        bool $expectHasExplicitPort
    ): void {
        $hp = null;
        try {
            $hp = HostAndPort::fromString($hpString);
        } catch (\Exception $e) {
            // Make sure we expected this.
            $this->assertNull($expectHost);
            return;
        }
        $this->assertNotNull($expectHost);

        // Apply withDefaultPort(), yielding hp2.
        $badDefaultPort = ($defaultPort < 0 || $defaultPort > 65535);
        $hp2 = null;
        try {
            $hp2 = $hp->withDefaultPort($defaultPort);
            $this->assertFalse($badDefaultPort);
        } catch (\Exception $e) {
            $this->assertTrue($badDefaultPort);
        }

        // Check the pre-withDefaultPort() instance.
        if ($expectHasExplicitPort) {
            $this->assertTrue($hp->hasPort());
            $this->assertEquals($expectPort, $hp->getPort());
        } else {
            $this->assertFalse($hp->hasPort());
            try {
                $hp->getPort();
            } catch (\Exception $expected) {
            }
        }
        $this->assertEquals($expectHost, $hp->getHost());

        // Check the post-withDefaultPort() instance (if any).
        if (!$badDefaultPort) {
            try {
                $port = $hp2->getPort();
                $this->assertTrue($expectPort != -1);
                $this->assertEquals($expectPort, $port);
            } catch (\Exception $e) {
                // Make sure we expected this to fail.
                $this->assertEquals(-1, $expectPort);
            }
            $this->assertEquals($expectHost, $hp2->getHost());
        }
    }

    public function testFromParts(): void
    {
        $hp = HostAndPort::fromParts("gmail.com", 81);
        $this->assertEquals("gmail.com", $hp->getHost());
        $this->assertTrue($hp->hasPort());
        $this->assertEquals(81, $hp->getPort());

        try {
            HostAndPort::fromParts("gmail.com:80", 81);
        } catch (\Exception $expected) {
        }

        try {
            HostAndPort::fromParts("gmail.com", -1);
        } catch (\Exception $expected) {
        }
    }

    public function testFromHost(): void
    {
        $hp = HostAndPort::fromHost("gmail.com");
        $this->assertEquals("gmail.com", $hp->getHost());
        $this->assertFalse($hp->hasPort());

        $hp = HostAndPort::fromHost("[::1]");
        $this->assertEquals("::1", $hp->getHost());
        $this->assertFalse($hp->hasPort());

        try {
            HostAndPort::fromHost("gmail.com:80");
        } catch (\Exception $expected) {
        }

        try {
            HostAndPort::fromHost("[gmail.com]");
        } catch (\Exception $expected) {
        }
    }

    public function testGetPortOrDefault(): void
    {
        $this->assertEquals(80, HostAndPort::fromString("host:80")->getPortOrDefault(123));
        $this->assertEquals(123, HostAndPort::fromString("host")->getPortOrDefault(123));
    }

    public function testHashCodeAndEquals(): void
    {
        $hpNoPort1 = HostAndPort::fromString("foo::123");
        $hpNoPort2 = HostAndPort::fromString("foo::123");
        $hpNoPort3 = HostAndPort::fromString("[foo::123]");
        $hpNoPort4 = HostAndPort::fromHost("[foo::123]");
        $hpNoPort5 = HostAndPort::fromHost("foo::123");

        $hpWithPort1 = HostAndPort::fromParts("[foo::123]", 80);
        $hpWithPort2 = HostAndPort::fromParts("foo::123", 80);
        $hpWithPort3 = HostAndPort::fromString("[foo::123]:80");

        $this->assertTrue($hpNoPort1->getHost() == ($hpNoPort2->getHost() == ($hpNoPort3->getHost() == ($hpNoPort4->getHost() == $hpNoPort5->getHost()))));
        $this->assertTrue($hpWithPort1->getHost() == ($hpWithPort2->getHost() == $hpWithPort3->getHost()));
    }

    public function testRequireBracketsForIPv6(): void
    {
        // Bracketed IPv6 works fine.
        $this->assertEquals("::1", HostAndPort::fromString("[::1]")->requireBracketsForIPv6()->getHost());
        $this->assertEquals("::1", HostAndPort::fromString("[::1]:80")->requireBracketsForIPv6()->getHost());
        // Non-bracketed non-IPv6 works fine.
        $this->assertEquals("x", HostAndPort::fromString("x")->requireBracketsForIPv6()->getHost());
        $this->assertEquals("x", HostAndPort::fromString("x:80")->requireBracketsForIPv6()->getHost());

        // Non-bracketed IPv6 fails.
        try {
            HostAndPort::fromString("::1")->requireBracketsForIPv6();
        } catch (\Exception $expected) {
        }
    }

    public function testToString(): void
    {
        // With ports.
        $this->assertEquals("foo:101", "" . HostAndPort::fromString("foo:101"));
        $this->assertEquals(":102", "" . HostAndPort::fromString(":102"));
        $this->assertEquals("[1::2]:103", "" . HostAndPort::fromParts("1::2", 103));
        $this->assertEquals("[::1]:104", "" . HostAndPort::fromString("[::1]:104"));

        // Without ports.
        $this->assertEquals("foo", "" . HostAndPort::fromString("foo"));
        $this->assertEquals("", "" . HostAndPort::fromString(""));
        $this->assertEquals("[1::2]", "" . HostAndPort::fromString("1::2"));
        $this->assertEquals("[::1]", "" . HostAndPort::fromString("[::1]"));

        // Garbage in, garbage out.
        $this->assertEquals("[::]]:107", "" . HostAndPort::fromParts("::]", 107));
        $this->assertEquals("[[:]]:108", "" . HostAndPort::fromString("[[:]]:108"));
    }
}
