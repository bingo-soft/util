<?php

namespace Util\Net\NameService\Dns;

use Util\Net\Url\{
    Uri,
    UrlUtil
};

class DnsUrl extends Uri
{
    private $domain;

    //valid format dns:[//host[:port]][/domain] - https://download.oracle.com/otn_hosted_doc/jdeveloper/904preview/jdk14doc/docs/guide/jndi/jndi-dns.html#URL
    public function __construct(string $url)
    {
        parent::__construct($url);

        if ($this->scheme != "dns") {
            throw new \Exception($url . " is not a valid DNS pseudo-URL");
        }

        $this->domain = strpos($this->path, "/") === 0
            ? substr($this->path, 1)
            : $this->path;

        $this->domain = $this->domain == ''
            ? "."
            : UrlUtil::decode($this->domain);
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}
