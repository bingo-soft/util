<?php

namespace Util\Net\Naming;

interface InitialContextFactoryBuilderInterface
{
    public function createInitialContextFactory(array $environment): InitialContextFactoryInterface;
}
