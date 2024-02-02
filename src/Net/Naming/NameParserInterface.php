<?php

namespace Util\Net\Naming;

interface NameParserInterface
{
    public function parse(string $name): NameInterface;
}
