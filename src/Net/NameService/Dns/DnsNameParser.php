<?php

namespace Util\Net\NameService\Dns;

use Util\Net\Naming\{
    NameInterface,
    NameParserInterface
};

class DnsNameParser implements NameParserInterface
{
    public function parse(string $name): NameInterface
    {
        return new DnsName($name);
    }


    // Every DnsNameParser is created equal.

    public function equals($obj): bool
    {
        return ($obj instanceof DnsNameParser);
    }
}
