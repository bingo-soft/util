<?php

namespace Util\Net;

class SocksProxy extends Proxy
{
    private int $version = 0;

    private function __construct(SocketAddress $addr, int $version)
    {
        parent::__construct(ProxyType::SOCKS, $addr);
        $this->version = $version;
    }

    public static function create(SocketAddress $addr, int $version): SocksProxy
    {
        return new SocksProxy($addr, $version);
    }

    public function protocolVersion(): int
    {
        return $this->version;
    }
}
