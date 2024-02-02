<?php

namespace Util\Net\NameService;

interface NameServiceInterface
{
    public function lookupAllHostAddr(string $host): ?array;

    public function getHostByAddr(array | string $addr): ?string;
}
