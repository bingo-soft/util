<?php

namespace Util\Net;

class Inet4AddressImpl implements InetAddressImplInterface
{
    private $anyLocalAddress;
    private $loopbackAddress;

    public function getLocalHostName()
    {
        return gethostname();
    }

    public function lookupAllHostAddr(string $hostname): ?array
    {
        return empty(gethostbynamel($hostname)) ? null : [ new Inet4Address($hostname, gethostbyname($hostname))];
    }

    public function getHostByAddr(array | string $addr)
    {
        if (is_string($addr)) {
            return gethostbyaddr($addr);
        } elseif (is_array($addr)) {
            $ip = (hexdec($addr[0]) & 0xff) . "." . (hexdec($addr[1]) & 0xff) . "." . (hexdec($addr[2]) & 0xff) . "." . (hexdec($addr[3]) & 0xff);
            return gethostbyaddr($addr);
        }
    }

    public function anyLocalAddress(): InetAddress
    {
        if ($this->anyLocalAddress == null) {
            $this->anyLocalAddress = new Inet4Address(); // {0x00,0x00,0x00,0x00}
            $this->anyLocalAddress->holder()->hostName = "0.0.0.0";
        }
        return $this->anyLocalAddress;
    }

    public function loopbackAddress(): InetAddress
    {
        if ($this->loopbackAddress == null) {
            $loopback = [0x7f, 0x00, 0x00, 0x01];
            $this->loopbackAddress = new Inet4Address("localhost", $loopback);
        }
        return $this->loopbackAddress;
    }
}
