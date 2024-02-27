<?php

namespace Util\Net;

class NonProxyInfo
{
    public const defStringVal = "localhost|127.*|[::1]|0.0.0.0|[::0]";

    public $hostsSource;
    public $hostsPool;
    public $property;
    public $defaultVal;
    public static $ftpNonProxyInfo;
    public static $httpNonProxyInfo;
    public static $socksNonProxyInfo;

    public function __construct(string $p, ?string $s = null, $pool = null, ?string $d = null)
    {
        $this->property = $p;
        $this->hostsSource = $s;
        $this->hostsPool = $pool;
        $this->defaultVal = $d;
    }

    public static function ftpNonProxyInfo(): NonProxyInfo
    {
        if (self::$ftpNonProxyInfo === null) {
            self::$ftpNonProxyInfo = new NonProxyInfo("ftp.nonProxyHosts", null, null, self::defStringVal);
        }
        return self::$ftpNonProxyInfo;
    }

    public static function httpNonProxyInfo(): NonProxyInfo
    {
        if (self::$httpNonProxyInfo === null) {
            self::$httpNonProxyInfo = new NonProxyInfo("http.nonProxyHosts", null, null, self::defStringVal);
        }
        return self::$httpNonProxyInfo;
    }

    public static function socksNonProxyInfo(): NonProxyInfo
    {
        if (self::$socksNonProxyInfo === null) {
            self::$socksNonProxyInfo = new NonProxyInfo("socksNonProxyHosts", null, null, self::defStringVal);
        }
        return self::$socksNonProxyInfo;
    }
}
