<?php

namespace Util\Net\Naming;

interface ObjectFactoryInterface
{
    public function getObjectInstance($obj, NameInterface $name, ContextInterface $nameCtx, array $environment);
}
