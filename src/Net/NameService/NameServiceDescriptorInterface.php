<?php

namespace Util\Net\NameService;

interface NameServiceDescriptorInterface
{
    /**
     * Create a new instance of the corresponding name service.
     */
    public function createNameService(): NameServiceInterface;

    /**
     * Returns this service provider's name
     *
     */
    public function getProviderName(): string;

    /**
     * Returns this name service type
     * "dns" "nis" etc
     */
    public function getType(): string;
}