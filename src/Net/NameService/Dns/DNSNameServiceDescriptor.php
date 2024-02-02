<?php

namespace Util\Net\NameService\Dns;

use Util\Net\NameService\{
    NameServiceDescriptorInterface,
    NameServiceInterface
};

class DNSNameServiceDescriptor implements NameServiceDescriptorInterface
{
    /**
     * Create a new instance of the corresponding name service.
     */
    public function createNameService(): NameServiceInterface
    {
        return new DNSNameService();
    }

    /**
     * Returns this service provider's name
     *
     */
    public function getProviderName(): string
    {
        return "default";
    }

    /**
     * Returns this name service type
     * "dns" "nis" etc
     */
    public function getType(): string
    {
        return "dns";
    }
}