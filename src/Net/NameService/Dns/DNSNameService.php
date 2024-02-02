<?php

namespace Util\Net\NameService\Dns;

use Util\Net\NameService\NameServiceInterface;

class DNSNameService implements NameServiceInterface
{
    // List of domains specified by property
    private $domainList = [];

    // JNDI-DNS URL for name servers specified via property
    private $nameProviderUrl = null; 
    
    //@TODO
}
