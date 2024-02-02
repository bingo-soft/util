<?php

namespace Util\Net\Naming;

interface ObjectFactoryBuilderInterface
{
    public function createObjectFactory($obj, array $environment): ?ObjectFactoryInterface;
}
