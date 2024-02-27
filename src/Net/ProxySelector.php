<?php

namespace Util\Net;


abstract class ProxySelector
{
    private static $PROXY_SELECTOR = null;

    public static function getDefault(): ProxySelector
    {
        //@TODO - can be confgured vie environment variable or configuration files
        if (self::$PROXY_SELECTOR === null) {
            self::$PROXY_SELECTOR = new DefaultProxySelector();
        }
        return self::$PROXY_SELECTOR;
    }
}
