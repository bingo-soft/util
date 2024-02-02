<?php

namespace Util\Net;

interface InetAddressImplInterface
{
    public function getLocalHostName();
    public function lookupAllHostAddr(string $hostname): ?array;
    public function getHostByAddr(array | string $addr);
    public function anyLocalAddress(): InetAddress;
    public function loopbackAddress(): InetAddress;
}
