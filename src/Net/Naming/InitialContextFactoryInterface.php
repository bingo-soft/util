<?php

namespace Util\Net\Naming;

interface InitialContextFactoryInterface
{
    public function getInitialContext(array $environment): ?ContextInterface;
}
