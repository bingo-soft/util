<?php

namespace Tests\Net;

use Util\Net\Util\IPAddressUtil;
use PHPUnit\Framework\TestCase;

class IPAddressUtilTest extends TestCase
{
    public function testTextToNumeric(): void
    {
        $arr = IPAddressUtil::textToNumericFormatV4("192.168.0.5");
        $this->assertEquals([0xc0, 0xa8, 0x00, 0x05], $arr);

        $arr = IPAddressUtil::textToNumericFormatV6("::192.168.0.1");
        $this->assertEquals([0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0xc0, 0xa8, 0x00, 0x01], $arr);

        $arr = IPAddressUtil::textToNumericFormatV6("::ffff:192.168.0.1");
        $this->assertEquals([0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0xff, 0xff, 0xc0, 0xa8, 0x00, 0x01], $arr);

        $arr = IPAddressUtil::textToNumericFormatV6("::1");
        $this->assertEquals([0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x01], $arr);

        $arr = IPAddressUtil::textToNumericFormatV6("2001:db8::1");
        $this->assertEquals([0x20, 0x01, 0x0d, 0xb8, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x01], $arr);

        //check multicast addresses
        $this->assertMulticast(127, 224, false);
        $this->assertMulticast(224, 240, true);
        $this->assertMulticast(240, 255, false);

        //loopback checking
        $addr = IPAddressUtil::textToNumericFormatV4("127.0.0.1");
        $address = $this->getAddressAsInt($addr);

        $newAddr = [];
        $newAddr[0] = ($address >> 24) & 0xFF;
        $newAddr[1] = ($address >> 16) & 0xFF;
        $newAddr[2] = ($address >> 8) & 0xFF;
        $newAddr[3] = $address & 0xFF;
        $this->assertEquals(127, $newAddr[0]);

        $addr = IPAddressUtil::textToNumericFormatV4("169.254.0.0");
        $address = $this->getAddressAsInt($addr);
        $this->assertTrue(((($address >> 24) & 0xFF) == 169) && ((($address >> 16) & 0xFF) == 254));
    }

    private function assertMulticast(int $start, int $end, bool $test): void
    {
        for ($i = $start; $i < $end; $i += 1) {
            $addr = IPAddressUtil::textToNumericFormatV4("$i.0.0.1");
            $address = $this->getAddressAsInt($addr);
            if ($test) {
                $this->assertTrue(($address & 0xf0000000) == 0xe0000000);
            } else {
                $this->assertFalse(($address & 0xf0000000) == 0xe0000000);
            }

            $addr = IPAddressUtil::textToNumericFormatV4("$i.255.255.255");
            $address = $this->getAddressAsInt($addr);
            if ($test) {
                $this->assertTrue(($address & 0xf0000000) == 0xe0000000);
            } else {
                $this->assertFalse(($address & 0xf0000000) == 0xe0000000);
            }
        }
    }

    private function getAddressAsInt(array $addr): int
    {
        $address = $addr[3] & 0xFF;
        $address |= (($addr[2] << 8) & 0xFF00);
        $address |= (($addr[1] << 16) & 0xFF0000);
        $address |= (($addr[0] << 24) & 0xFF000000);
        return $address;
    }
}
