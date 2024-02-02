<?php

namespace Util\Net;

class InetAddressHolder
{
    public $originalHostName;
    public $hostName;
    public int $address;
    public int $family;

    public function __construct(?string $hostName = null, ?int $address = 0, ?int $family = 0) {
        $this->originalHostName = $hostName;
        $this->hostName = $hostName;
        $this->address = $address;
        $this->family = $family;
    }

    public function init(string $hostName, int $family): void
    {
        $this->originalHostName = $hostName;
        $this->hostName = $hostName;
        if ($family != -1) {
            $this->family = $family;
        }
    }
    
    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    public function getOriginalHostName(): ?string
    {
        return $this->originalHostName;
    }
    
    public function getAddress(): int
    {
        return $this->address;
    }
    
    public function getFamily(): int
    {
        return $this->family;
    }
}
