<?php

namespace Util\Net;

class InetSocketAddressHolder
{
    // The hostname of the Socket Address
    private $hostname;
    // The IP address of the Socket Address
    private $addr;
    // The port number of the Socket Address
    private int $port = -1;

    public function __construct(?string $hostname, ?InetAddress $addr, ?int $port = -1)
    {
        $this->hostname = $hostname;
        $this->addr = $addr;
        $this->port = $port;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getAddress(): InetAddress
    {
        return $this->addr;
    }

    public function getHostName(): ?string
    {
        if ($this->hostname != null)
            return $this->hostname;
        if ($this->addr != null)
            return $this->addr->getHostName();
        return null;
    }

    public function getHostString(): ?string
    {
        if ($this->hostname != null)
            return $this->hostname;
        if ($this->addr != null) {
            if ($this->addr->holder()->getHostName() != null) {
                return $this->addr->holder()->getHostName();
            } else {
                return $this->addr->getHostAddress();
            }
        }
        return null;
    }

    public function isUnresolved(): bool
    {
        return $this->addr == null;
    }
  
    public function __toString(): string
    {
        if ($this->isUnresolved()) {
            return $this->hostname . ":" . $this->port;
        } else {
            return $this->addr . ":" . $this->port;
        }
    }

    public function equals($obj): bool
    {
        if ($obj == null || !($obj instanceof InetSocketAddressHolder)) {
            return false;
        }
        $sameIP = false;
        if ($this->addr != null) {
            $sameIP = $this->addr == $obj->addr;
        } elseif ($this->hostname != null) {
            $sameIP = $obj->addr == null && strtolower($this->hostname) == strtolower($obj->hostname);
        } else {
            $sameIP = $obj->addr == null && $obj->hostname == null;
        }
        return $sameIP && $this->port == $obj->port;
    }
}