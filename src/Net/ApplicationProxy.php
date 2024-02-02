<?php

namespace Util\Net;

class ApplicationProxy extends Proxy
{
    private function __construct(Proxy $proxy)
    {
        parent::__construct($proxy->type(), $proxy->address());
    }

    public static function create(Proxy $proxy): ApplicationProxy
    {
        return new ApplicationProxy($proxy);
    }
}