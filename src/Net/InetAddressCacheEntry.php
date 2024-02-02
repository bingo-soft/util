<?php

namespace Util\Net;

class InetAddressCacheEntry
{
    public array $addresses = [];
    public int $expiration = 0;

    public function __construct(array $addresses, int $expiration)
    {
        $this->addresses = $addresses;
        $this->expiration = $expiration;
    }    
}
