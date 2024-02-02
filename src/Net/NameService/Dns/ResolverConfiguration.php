<?php

namespace Util\Net\NameService\Dns;

abstract class ResolverConfiguration
{
    private static ResolverConfiguration $provider;

    public static function open(): ResolverConfiguration
    {
        if (self::$provider == null) {
            $provider = new ResolverConfigurationImpl();
        }
        return $provider;
    }

    abstract public function searchlist(): array;

    abstract public function nameservers(): array;
}
