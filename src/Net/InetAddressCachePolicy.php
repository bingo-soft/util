<?php

namespace Util\Net;

class InetAddressCachePolicy
{
    private const CACHE_POLICY_PROP = "networkaddress.cache.ttl";
    private const NEGATIVE_CACHE_POLICY_PROP = "networkaddress.cache.negative.ttl";

    public const FOREVER = -1;
    public const NEVER = 0;
    public const DEFAULT_POSITIVE = 30;

    private static int $cachePolicy = self::FOREVER;
    private static int $negativeCachePolicy = self::NEVER;

    private static bool $propertySet = false;

    public function __construct(?string $resourcePath = 'src/Resources/php.security')
    {
        if (!self::$propertySet) {
            if (file_exists($resourcePath)) {
                $props = [];
                $fp = fopen($resourcePath, "r+");       
                while (($line = fgets($fp, 4096)) !== false) {
                    $tokens = explode("=", $line);
                    if (count($tokens) == 2) {
                        $props[$tokens[0]] = trim($tokens[1]);
                    }
                }
                fclose($fp);

                if (array_key_exists(self::CACHE_POLICY_PROP, $props)) {
                    self::$cachePolicy = intval($props[self::CACHE_POLICY_PROP]);
                    if (self::$cachePolicy < 0) {
                        self::$cachePolicy = self::FOREVER;
                    }
                } else {
                    self::$cachePolicy = self::DEFAULT_POSITIVE;
                }

                if (array_key_exists(self::NEGATIVE_CACHE_POLICY_PROP, $props)) {
                    self::$negativeCachePolicy = intval($props[self::NEGATIVE_CACHE_POLICY_PROP]);
                    if (self::$negativeCachePolicy < 0) {
                        self::$negativeCachePolicy = self::FOREVER;
                    }
                }
            }
            self::$propertySet = true;
        }
    }

    public function get(): int
    {
        return self::$cachePolicy;
    }

    public function getNegative(): int
    {
        return self::$negativeCachePolicy;
    }

    public function setInetAddressCacheTTL(int $cachePolicy, int $negativeCachePolicy): void
    {
        self::$cachePolicy = $cachePolicy;
        self::$negativeCachePolicy = $negativeCachePolicy;
    }
}
