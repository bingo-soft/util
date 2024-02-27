<?php

namespace Util\Net;

class NetProperties
{
    private static $props = [];
    private const NETWORK_RESOURCE_FILE_NAME = 'src/Resources/net.properties';

    private function __construct()
    {
    }

    private static function loadDefaultProperties(string $resourceFile): void
    {
        if (empty(self::$props)) {
            $props = [];
            if (file_exists($resourceFile)) {                
                $fp = fopen($resourceFile, "r");       
                while (($line = fgets($fp, 4096)) !== false) {
                    $tokens = explode("=", $line);
                    if (count($tokens) == 2) {
                        $props[$tokens[0]] = trim($tokens[1]);
                    }
                }
                fclose($fp);
            }
            self::$props = array_merge(getenv(), $props);
        }
    }

    public static function get(string $key, ?array $props = [], ?int $defaultValue = null, ?string $resourceFile = self::NETWORK_RESOURCE_FILE_NAME)
    {
        if (empty($props)) {
            self::loadDefaultProperties($resourceFile);
            $props = self::$props;
        }
        if (array_key_exists($key, $props)) {
            return $props[$key];
        } elseif ($defaultValue !== null) {
            return $defaultValue;
        }
        return null;
    }
}
