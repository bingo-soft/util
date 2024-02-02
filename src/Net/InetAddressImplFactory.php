<?php

namespace Util\Net;

class InetAddressImplFactory
{
    public const IPV6_SUPPORTED = 'IPV6_SUPPORTED';

    public static function create(): InetAddressImplInterface
    {
        return InetAddress::loadImpl(self::isIPv6Supported() ? "Inet6AddressImpl" : "Inet4AddressImpl");
    }

    public static function isIPv6Supported(): bool
    {
        return boolval(getenv(self::IPV6_SUPPORTED));
    }
}
