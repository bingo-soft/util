<?php

namespace Util\Net\Naming;

interface StateFactoryInterface
{
    public function getStateToBind($obj, NameInterface $name, ContextInterface $nameCtx, array $environment);
}
