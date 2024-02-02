<?php

namespace Util\Net\Naming;

interface ResolverInterface
{
    public function resolveToClass(NameInterface | string $name, string $contextType): ResolveResult;
}
